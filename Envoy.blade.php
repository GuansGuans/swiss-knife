@servers(['local' => 'localhost'])

@setup
    $localCwd = '/Users/yaozm/Documents/wwwroot/swiss-knife/';
    $localPhp = '/opt/homebrew/opt/php@8.5/bin/php';
    $localComposer = "$localPhp /opt/homebrew/bin/composer --no-interaction --ansi -v";
    $localArtisan = "$localPhp artisan --ansi -v";
@endsetup

@task('local-local', ['on' => ['local'], 'parallel' => false])
    cd {{ $localCwd }}
    sed -i '' -e 's/^APP_DEBUG=false/APP_DEBUG=true/' -e 's/^APP_ENV=production/APP_ENV=local/' .env
    {{ $localComposer }} install
    {{ $localComposer }} app:install
    {{ $localArtisan }} optimize:clear
    {{ $localArtisan }} about
@endtask

@task('local-production', ['on' => ['local'], 'confirm' => true, 'parallel' => false])
    cd {{ $localCwd }}
    sed -i '' -e 's/^APP_DEBUG=true/APP_DEBUG=false/' -e 's/^APP_ENV=local/APP_ENV=production/' .env
    {{ $localComposer }} install --no-dev
    {{ $localComposer }} app:install
    {{ $localArtisan }} optimize
    {{ $localArtisan }} about
@endtask
