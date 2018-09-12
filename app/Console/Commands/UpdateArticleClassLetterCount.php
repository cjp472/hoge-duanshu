<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models;

class UpdateArticleClassLetterCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updatearticleclasslettercount {shop_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新图文课时字数';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

        /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $shopId = $this->argument('shop_id');
        $shops = [];
        if ($shopId) {
            $shops = Models\Shop::where('hashid', $shopId)->get();
        } else {
            $shops = Models\Shop::get();
        }

        foreach ($shops as $s) {
            $this->updateCoursesLetterCount($s);
        }

    }

    public function updateCoursesLetterCount($shop)
    {
        echo "$shop->title $shop->hashid\n";
        $courses = Models\Course::where('shop_id', $shop->hashid)->where('course_type','article')->get();
        foreach ($courses as $c) {
            $this->updateCourseLetterCount($c);
        }
        echo "\n";
    }

    public function updateCourseLetterCount($course)
    {   
        echo "  $course->title $course->hashid\n";
        $classes = Models\ClassContent::where(['course_id'=>$course->hashid])->get();
        foreach ($classes as $c) {
            $this->updateClassLetterCount($c);
        }
    }

    public function updateClassLetterCount($class)
    {   
        try{
            $content = unserialize($class->content)['content'];
        }catch(\Exception $e){
            echo "      ignore $class->title $class->id\n";
            return;
        }
        
        $readableLetter = preg_replace('/<[^<]+?>/', "", $content);
        $letterCount = mb_strlen($readableLetter);
        Models\ClassContent::where('id',$class->id)->update(['letter_count'=>$letterCount]);
        echo "      update $class->title $class->id  letter $letterCount\n";

    }

}
