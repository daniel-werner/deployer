<?php declare(strict_types=1);
namespace Deployer;

set('domain', function () {
    return ask(' Domain: ', get('hostname'));

});

set('public_path', function () {
    return ask(' Public path: ', 'public');
});

desc('Provision nginx website');
task('provision:nginx:website', function () {

    if (get('remote_user') !== 'root' && get('become') !== 'root') {
        set('become', 'root');
    }

    $deployUser = get('remote_user');

    run("[ -d {{deploy_path}} ] || mkdir {{deploy_path}}");
    run("chown $deployUser:$deployUser {{deploy_path}}");

    $domain = get('domain');
    $phpVersion = get('php_version');
    $deployPath = run("realpath {{deploy_path}}");
    $publicPath = get('public_path');

    run("[ -d {{deploy_path}}/logs ] || mkdir {{deploy_path}}/logs");
    run("chown www-data:www-data {{deploy_path}}/logs");

    $nginxfile = <<<EOF
server {
\tlisten 80;
\tlisten [::]:80;

\taccess_log $deployPath/logs/access.log;
\terror_log $deployPath/logs/error.log;

\troot $deployPath/current/$publicPath;

\t# Add index.php to the list if you are using PHP
\tindex index.php index.html index.htm index.nginx-debian.html;

\tserver_name $domain;

\tlocation / {
\t\t# First attempt to serve request as file, then
\t\t# as directory, then fall back to displaying a 404.
\t\ttry_files \$uri \$uri/ /index.php?\$query_string;
\t}

\t# pass PHP scripts to FastCGI server
\t#
\tlocation ~ \.php$ {
\t\tinclude snippets/fastcgi-php.conf;

\t\t# With php-fpm (or other unix sockets):
\t\tfastcgi_pass unix:/var/run/php/php$phpVersion-fpm.sock;
\t\tfastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
\t}

\t# deny access to .htaccess files, if Apache\'s document root
\t# concurs with nginx\'s one
\t#
\tlocation ~ /\.ht {
\t\tdeny all;
\t}
}
EOF;

    $nginxConfFile = "/etc/nginx/sites-available/$domain.conf";

    if (test("[ -f $nginxConfFile ]")) {
        run("echo $'$nginxfile' > $nginxConfFile.new");
        $diff = run("diff -U5 --color=always $nginxConfFile $nginxConfFile.new", ['no_throw' => true]);
        if (empty($diff)) {
            run("rm $nginxConfFile.new");
        } else {
            info('Found changes');
            writeln("\n" . $diff);
            $answer = askChoice(' Which file to save? ', ['old', 'new'], 0);
            if ($answer === 'old') {
                run("rm $nginxConfFile.new");
            } else {
                run("mv $nginxConfFile.new $nginxConfFile");
            }
        }
    } else {
        run("echo $'$nginxfile' > $nginxConfFile");
    }

    if (!test("[ -f /etc/nginx/sites-enabled/$domain.conf ]")) {
        run("ln -s $nginxConfFile /etc/nginx/sites-enabled/$domain.conf");
    }

    info("Checking nginx configuration");
    $result = run('nginx -t');

    if (!empty($result)) {
        error($result);
    }

    info("Nginx configuration check passed... ok");

    if (askChoice('Would you like to reload nginx config? y/n', ['y', 'n'], 'y') == 'y') {
        info("Reloading nginx config");
        run('service nginx reload');
    }

    info("Website $domain configured!");
})->limit(1);

task('provision:website:secure', ['provision:certificate']);
