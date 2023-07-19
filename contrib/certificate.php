<?php declare(strict_types=1);
namespace Deployer;

set('domain', function () {
    return ask(' Domain: ', get('hostname'));
});

task('provision:certificate', [
    'provision:certbot:install',
    'provision:certificate:install',
]);

desc('Install certbot');
task('provision:certbot:install', function () {

    if (get('remote_user') !== 'root' && get('become') !== 'root') {
        set('become', 'root');
    }

    if(test('[ -x "$(command -v certbot)" ]')) {
        info('certbot already installed...ok');
        return;
    }

    if(!test('[ -x "$(command -v snap)" ]')) {
        $shouldInstall = askChoice('snapd was not found, would you like to install? y/n', ['y', 'n'], 'y');
        if ($shouldInstall !== 'y') {
            return;
        }

        run("apt install snapd -y");

    } else {
        info('Snapd already installed...ok');
    }

    $shouldInstall = askChoice('certbot was not found, would you like to install? y/n', ['y', 'n'], 'y');

    if ($shouldInstall !== 'y') {
        return;
    }

    run("snap install core; snap refresh core");
    run("snap install --classic certbot");
    run("ln -s /snap/bin/certbot /usr/bin/certbot");

})->limit(1);

task('provision:certificate:install', function () {
    if (get('remote_user') !== 'root' && get('become') !== 'root') {
        set('become', 'root');
    }

    $domain = get('domain');

    $email = ask('Please provide your email for the Let\'s encrypt certificate:');

    $emailOption = !empty($email) ? '--email '.$email : '--register-unsafely-without-email';

    run("certbot --nginx --noninteractive --agree-tos --cert-name $domain -d $domain $emailOption");
});
