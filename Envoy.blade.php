@servers(['local' => 'localhost'])

@setup
    $localPhp = '/opt/homebrew/opt/php@8.5/bin/php';
    $localComposer = "$localPhp /opt/homebrew/bin/composer --no-interaction --ansi -v";
    $localArtisan = "$localPhp artisan --ansi -v";
@endsetup

@task('local', ['on' => ['local'], 'parallel' => false])
    cd /Users/yaozm/Documents/wwwroot/clothing-gm
    {{ $localArtisan }} clear:all
    {{ $localComposer }} dump-autoload --optimize
    {{ $localArtisan }} schedule-monitor:sync

    {{ $localArtisan }} opcache:clear
    {{ $localArtisan }} opcache:config
    {{ $localArtisan }} opcache:status
    {{ $localArtisan }} opcache:compile
@endtask

@task('testing', ['on' => ['testing'], 'confirm' => true, 'parallel' => false])
    cd /data/vhosts/gmtools-test.dreams.howanjoy.com/
    {{ $testingArtisan }} down --redirect=/ --secret='ba401813-40a0-42d2-bdd2-1c7f55c70ccb'
    git checkout -- .
    git switch testing
    git pull origin testing
    {{-- git pull gitee testing --}}
    {{ $testingComposer }} install --no-interaction --prefer-dist --optimize-autoloader --no-dev
    {{ $testingArtisan }} clear:all --force
    {{ $testingArtisan }} optimize:all --force
    {{ $testingArtisan }} up

    {{ $testingArtisan }} schedule-monitor:sync
    {{ $testingArtisan }} queue:restart

    {{ $testingArtisan }} opcache:clear
    {{ $testingArtisan }} opcache:config
    {{ $testingArtisan }} opcache:status
    {{ $testingArtisan }} opcache:compile

    {{ $testingArtisan }} health:check --except=*Queue --except=*SqlMode --except=*SqlSafeUpdates
@endtask
