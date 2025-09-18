<?php

    use Coco\mediaMigration\Migration;
    use Coco\mediaMigration\Processor;

    require '../vendor/autoload.php';

    Migration::initLogger('migration-log', true);

    $path = './media/iphone';
    $dest = './result/iphone';

    $migration = new Migration($path, true);

    $migration->addProcessor(new Processor(Migration::filterExt('jpg,png'), Migration::processorPhotoIphoneCompress($dest, true)));
    $migration->addProcessor(new Processor(Migration::filterExt('mp4,mov'), Migration::processorVideoIphoneCompress($dest, true)));

    $migration->run();