<?php

    use Coco\mediaMigration\Migration;
    use Coco\mediaMigration\Processor;

    require '../vendor/autoload.php';

    Migration::initLogger('migration-log', true);

    $path = '/var/phone_media/source/';
    $dest = '/var/phone_media/myMedia/weixin';

//    $path = './media/weixin/zzz';
//    $dest = './result/weixin';

    $migration = new Migration($path, true);

    // mmexport1627439799891.jpg
    // wx_camera_1572342510242.jpg
    $migration->addProcessor(new Processor(Migration::filterRegex('#(?<!\d)(1[5-7]\d{8}).+(jpg|png)#iu'), Migration::processorImage(function(SplFileInfo $fileInfo, Migration $_this) use ($dest) {
        $dest = rtrim($dest, '/') . '/';

        $path = $fileInfo->getRealPath();
        preg_match('#(?<!\d)(1[5-7]\d{8}).+(jpg|png)#iu', $path, $result);

        if (isset($result[1]))
        {
            $createTime = $result[1];
        }
        else
        {
            $createTime = $fileInfo->getMTime();
        }
        $year  = date('Y', $createTime);
        $month = date('m', $createTime);

        $nameWithoutExt = explode('.', $fileInfo->getFilename())[0];

        $destName = implode('', [
            $dest,
            $year . '-' . $month . '/',
            $fileInfo->getFilename(),
        ]);

        return $destName;
    }, false)));

    // Mmexport1631527039295_out.mp4
    // Wx_Camera_1560658981615_out.mp4
    $migration->addProcessor(new Processor(Migration::filterRegex('#(?<!\d)(1[5-7]\d{8}).+(mp4|mov)#iu'), Migration::processorVideo(function(SplFileInfo $fileInfo, Migration $_this) use ($dest) {
        $dest = rtrim($dest, '/') . '/';

        $path = $fileInfo->getRealPath();
        preg_match('#(?<!\d)(1[5-7]\d{8}).+(mp4|mov)#iu', $path, $result);

        if (isset($result[1]))
        {
            $createTime = $result[1];
        }
        else
        {
            $createTime = $fileInfo->getMTime();
        }
        $year  = date('Y', $createTime);
        $month = date('m', $createTime);

        $nameWithoutExt = explode('.', $fileInfo->getFilename())[0];

        $destName = implode('', [
            $dest,
            $year . '-' . $month . '/',
            $fileInfo->getFilename(),
        ]);

        return $destName;
    }, false)));

    //Screenshot_2015-03-01-20-10-00.png
    //Screenshot_20221202_092337_com.moji.mjweather.jpg
    $migration->addProcessor(new Processor(Migration::filterRegex('#(20(?:1[2-9]|2[0-5]))-?(0[1-9]|1[0-2]).+(jpg|png)#iu'), Migration::processorImage(function(SplFileInfo $fileInfo, Migration $_this) use ($dest) {
        $dest = rtrim($dest, '/') . '/';

        $path = $fileInfo->getRealPath();
        preg_match('#(20(?:1[2-9]|2[0-5]))-?(0[1-9]|1[0-2]).+(jpg|png)#iu', $path, $result);

        if (isset($result[1]))
        {
            $year  = $result[1];
            $month = $result[2];
        }
        else
        {
            $createTime = $fileInfo->getMTime();
            $year       = date('Y', $createTime);
            $month      = date('m', $createTime);
        }

        $nameWithoutExt = explode('.', $fileInfo->getFilename())[0];

        $destName = implode('', [
            $dest,
            $year . '-' . $month . '/',
            $fileInfo->getFilename(),
        ]);

        return $destName;
    }, false)));

    //Vid_20190127_161821_out.mp4
    //VID_20230405_140314.mp4
    $migration->addProcessor(new Processor(Migration::filterRegex('#(20(?:1[2-9]|2[0-5]))-?(0[1-9]|1[0-2]).+(mp4|mov)#iu'), Migration::processorVideo(function(SplFileInfo $fileInfo, Migration $_this) use ($dest) {
        $dest = rtrim($dest, '/') . '/';

        $path = $fileInfo->getRealPath();
        preg_match('#(20(?:1[2-9]|2[0-5]))-?(0[1-9]|1[0-2]).+(mp4|mov)#iu', $path, $result);

        if (isset($result[1]))
        {
            $year  = $result[1];
            $month = $result[2];
        }
        else
        {
            $createTime = $fileInfo->getMTime();
            $year       = date('Y', $createTime);
            $month      = date('m', $createTime);
        }

        $nameWithoutExt = explode('.', $fileInfo->getFilename())[0];

        $destName = implode('', [
            $dest,
            $year . '-' . $month . '/',
            $fileInfo->getFilename(),
        ]);

        return $destName;
    }, false)));

    $migration->addProcessor(new Processor(Migration::filterAllFile(), Migration::processorMirrorDir($dest . '/none')));

    $migration->run();