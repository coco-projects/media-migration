<?php

    use Coco\mediaMigration\Migration;
    use Coco\mediaMigration\Processor;

    require '../vendor/autoload.php';

    $migration = new Migration('./media', true);

    $processor1 = new Processor(function(SplFileInfo $fileInfo, Migration $_this) {
        // /var/www/6025/new/coco-mediaMigration/examples/media/1.jpg
        $fileInfo->getRealPath();

        // /var/www/6025/new/coco-mediaMigration/examples/media/1.jpg
        $fileInfo->getPathname();

        // 1.jpg
        $fileInfo->getBasename();

        // /var/www/6025/new/coco-mediaMigration/examples/media
        $fileInfo->getPath();

        // 1.jpg
        $fileInfo->getFilename();

        // jpg
        $fileInfo->getExtension();

        // 228077
        $fileInfo->getSize();

        // 1757478380
        $fileInfo->getCTime();

        return $fileInfo->getExtension() == 'jpg';

    }, function(SplFileInfo $fileInfo, Migration $_this) {
        echo 'jpg->' . $fileInfo->getPathname();
        echo PHP_EOL;
    });

    $processor2 = new Processor(function(SplFileInfo $fileInfo, Migration $_this) {
        // /var/www/6025/new/coco-mediaMigration/examples/media/1.jpg
        $fileInfo->getPathname();

        // 1.jpg
        $fileInfo->getBasename();

        // 1.jpg
        $fileInfo->getBasename();

        // jpg
        $fileInfo->getExtension();

        // 228077
        $fileInfo->getSize();

        // 1757478380
        $fileInfo->getCTime();

        return $fileInfo->getExtension() == 'mp4';

    }, function(SplFileInfo $fileInfo, Migration $_this) {
        echo 'mp4->' . $fileInfo->getPathname();
        echo PHP_EOL;
    });

    $migration->addProcessor($processor1);
    $migration->addProcessor($processor2);
//    $migration->addProcessor(new Processor(Migration::filterAllFile(), Migration::processorMirrorDir('./result')));

    $migration->run();