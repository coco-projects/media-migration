<?php

    use Coco\mediaMigration\Migration;
    use Coco\mediaMigration\Processor;

    require '../vendor/autoload.php';

    Migration::initLogger('migration-log', true);
    $path = './media/weixin';
    $dest = './result/weixin';

    $migration = new Migration($path, true);

    $migration->addProcessor(new Processor(Migration::filterExt('jpg,png'), Migration::processorMirrorImageCompress($dest)));
    $migration->addProcessor(new Processor(Migration::filterExt('mp4,mov'), Migration::processorMirrorVideoCompress($dest)));

    $migration->run();