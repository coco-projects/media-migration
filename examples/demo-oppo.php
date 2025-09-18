<?php

    use Coco\mediaMigration\Migration;
    use Coco\mediaMigration\Processor;

    require '../vendor/autoload.php';

    Migration::initLogger('migration-log', true);

    $path = './media/oppo';
    $dest = './result/oppo';

//    $path = '/var/phone_media/ttt/Camera';
//    $dest = '/var/phone_media/ttt/result';

    $migration = new Migration($path, true);

    $migration->addProcessor(new Processor(Migration::filterExt('jpg,png'), Migration::processorPhotoOppoCompress($dest, true)));
    $migration->addProcessor(new Processor(Migration::filterExt('mp4'), Migration::processorVideoOppoCompress($dest, true)));

    $migration->run();