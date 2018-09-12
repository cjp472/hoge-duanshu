<?php

namespace App\Console;

use App\Console\Commands\AddFundsCommond;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Commands\Inspire::class,
        Commands\FlowStatistice::class,
        Commands\ChangeVideoClass::class,
        Commands\CronStatistics::class,
        Commands\CronContentStatistics::class,
        Commands\ClearLogTemp::class,
        Commands\RefundOrderRetry::class,
        Commands\FightGroupRetry::class,
        Commands\GiftFlowAndStorage::class,
        Commands\PushTemplate::class,
        Commands\SettlementStorageFlux::class,
        Commands\FundsArrearsCommond::class,
        Commands\AddFundsCommond::class,
        Commands\membercardGiftMigrate::class,
        Commands\membercardOptionsUpdate::class,
        Commands\WeappSetLowestVersion::class,
        Commands\ShopExpireSettlement::class,
        Commands\PinTuanRefund::class,
        Commands\PinTuanComplete::class,
        Commands\ShopH5HostCommand::class,
        Commands\CoursePaiedToStudent::class,
        Commands\CoursePreViewer::class,
        Commands\UpdateArticleClassLetterCount::class,
        Commands\TestCommand::class,
        Commands\ShopPromotionCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('change:video:class')->everyMinute();
        $schedule->command('cron:statistics')->daily();
        $schedule->command('cron:content:statistics')->daily();
        $schedule->command('clear:temp:log')->daily();
        $schedule->command('refund:order:retry')->daily();
        $schedule->command('fight:group:retry')->daily();
        $schedule->command('push:template')->everyMinute();
        //店铺过期任务 每天凌晨3:00执行
        $schedule->command('shop:expire')->dailyAt('03:00');
        //流量存储赠送 每个月第一天赠送一次
        $schedule->command('gift:flux:storage')->monthly();
        //店铺流量存储统计 每天18:00执行
        $schedule->command('storage:flux:settlement')->dailyAt('18:00');
        //店铺欠费任务 每天19:00执行
        $schedule->command('funds:arrears')->dailyAt('19:00');
    }
}