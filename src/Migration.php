<?php

    namespace Coco\mediaMigration;

    use Coco\logger\Logger;
    use FFMpeg\Format\Video\X264;
    use RecursiveDirectoryIterator;
    use RecursiveIteratorIterator;
    use Spatie\Image\Image;
    use Spatie\ImageOptimizer\OptimizerChainFactory;
    use SplFileInfo;

    class Migration
    {
        use Logger;

        protected array         $processor     = [];
        protected static string $logNamespace  = 'Migration-log';
        protected static bool   $enableEchoLog = false;

        public function __construct(public string $source, public bool $isRecursive = false)
        {
            if (!is_dir($this->source) or !is_readable($this->source))
            {
                throw new \Exception('Source file not found or not readable');
            }

            $this->source = realpath($this->source);

            if (static::$enableEchoLog)
            {
                $this->setStandardLogger(static::$logNamespace);
                $this->addStdoutHandler(static::getStandardFormatter());
            }
        }

        public function addProcessor(Processor $processor): static
        {
            $this->processor[] = $processor;

            return $this;
        }

        public function run(): void
        {
            if ($this->isRecursive)
            {
                $iterator     = new RecursiveDirectoryIterator($this->source);
                $fileIterator = new RecursiveIteratorIterator($iterator);
            }
            else
            {
                $iterator     = new \DirectoryIterator($this->source);
                $fileIterator = new \IteratorIterator($iterator);
            }

            foreach ($fileIterator as $fileInfo)
            {
                if (in_array($fileInfo->getBasename(), [
                    '.',
                    '..',
                ]))
                {
                    continue;
                }

                /**
                 * @var Processor $processor
                 */
                foreach ($this->processor as $processor)
                {
                    $isCompliant = call_user_func_array($processor->getRule(), [
                        $fileInfo,
                        $this,
                    ]);

                    if ($isCompliant)
                    {
                        call_user_func_array($processor->getProcessor(), [
                            $fileInfo,
                            $this,
                        ]);
                        break;
                    }
                }
            }
        }

        public static function initLogger(string $logNamespace, bool $enableEchoLog = false): void
        {
            static::$logNamespace  = $logNamespace;
            static::$enableEchoLog = $enableEchoLog;
        }

        /*------------------------------------------------------------------------*/

        /**
         * 内部压缩图片用
         *
         * @param string $originImagePath
         * @param string $destPath
         * @param bool   $deleteOriginOnDone
         *
         * @return string
         */
        protected function compressImage(string $originImagePath, string $destPath, bool $deleteOriginOnDone = false): string
        {
            if (!is_file($originImagePath))
            {
                return false;
            }

            if (!is_readable($originImagePath))
            {
                return false;
            }

            if (!is_writeable($originImagePath))
            {
                return false;
            }

            try
            {
                $optimizerChain = OptimizerChainFactory::create();
                $optimizerChain->useLogger($this->getLogger());

                Image::load($originImagePath)->setOptimizeChain($optimizerChain)->optimize()->save($destPath);

                $isSuccess = is_file($destPath);

                if (!$isSuccess)
                {
                    $this->logInfo("原图直接复制：" . $originImagePath . " -> " . $destPath);

                    copy($originImagePath, $destPath);
                }
            }
            catch (\Exception $exception)
            {
                $this->logInfo("处理失败：$originImagePath ，" . $exception->getMessage());
                $this->logInfo("原图直接复制：" . $originImagePath . " -> " . $destPath);

                copy($originImagePath, $destPath);
            }

            if ($deleteOriginOnDone)
            {
                $this->logInfo("删除原图：$originImagePath");
                unlink($originImagePath);
            }

            return $destPath;
        }

        /**
         * 内部压缩视频用
         *
         * @param string $originVideoPath
         * @param string $destPath
         * @param bool   $deleteOriginOnDone
         *
         * @return string
         */
        protected function compressVideo(string $originVideoPath, string $destPath, bool $deleteOriginOnDone = false): string
        {
            if (!is_file($originVideoPath))
            {
                return false;
            }

            if (!is_readable($originVideoPath))
            {
                return false;
            }

            if (!is_writeable($originVideoPath))
            {
                return false;
            }

            try
            {
                $ffmpegConfig = [
                    'ffmpeg.binaries'  => dirname(__DIR__) . '/bin/ffmpeg',
                    'ffprobe.binaries' => dirname(__DIR__) . '/bin/ffprobe',
                    'timeout'          => 36000,
                    'ffmpeg.threads'   => 12,
                ];

                $ffmpeg = \FFMpeg\FFMpeg::create($ffmpegConfig, $this->getLogger());

                $video = $ffmpeg->open($originVideoPath);

                $format = new X264();
                $format->on('progress', function($video, $format, $percentage) use ($originVideoPath) {
                    $this->logInfo($originVideoPath . ": $percentage% transcoded");
                });

                $format->setKiloBitrate(6000)->setAudioChannels(2)->setAudioKiloBitrate(256);

                $video->save($format, $destPath);

                $isSuccess = is_file($destPath);

                if (!$isSuccess)
                {
                    $this->logInfo("原视频直接复制：" . $originVideoPath . " -> " . $destPath);
                    copy($originVideoPath, $destPath);
                }
            }
            catch (\Exception $exception)
            {
                $this->logInfo("出错：" . $exception->getMessage());
                $this->logInfo("原视频直接复制：" . $originVideoPath . " -> " . $destPath);
                copy($originVideoPath, $destPath);
            }

            if ($deleteOriginOnDone)
            {
                $this->logInfo("删除原视频：$originVideoPath");
                unlink($originVideoPath);
            }

            return $destPath;
        }

        /*------------------------------------------------------------------------*/

        public static function filterExt(string $ext): \Closure
        {
            $ext = explode(',', $ext);

            $ext = array_map(function($item) {
                return trim($item);
            }, $ext);

            return function(SplFileInfo $fileInfo, Migration $_this) use ($ext) {
                return in_array(strtolower($fileInfo->getExtension()), $ext);
            };
        }

        public static function filterRegex(string $regex): \Closure
        {
            return function(SplFileInfo $fileInfo, Migration $_this) use ($regex) {
                return preg_match($regex, $fileInfo->getRealPath());
            };
        }

        public static function filterAllFile(): \Closure
        {
            return function(SplFileInfo $fileInfo, Migration $_this) {
                return true;
            };
        }


        public static function processorCopyFile(callable $destFunc): \Closure
        {
            return function(SplFileInfo $fileInfo, Migration $_this) use ($destFunc) {

                $destPath   = $destFunc($fileInfo, $_this);
                $originPath = $fileInfo->getRealPath();

                is_dir(dirname($destPath)) || mkdir(dirname($destPath), 0777, true);

                if (is_file($destPath))
                {
                    $_this->logInfo("文件存在，已经跳过：[$destPath]");
                }
                else
                {
                    $_this->logInfo("复制文件：[$originPath -> $destPath]");
                    copy($originPath, $destPath);
                }
            };
        }

        public static function processorImage(callable $destFunc): \Closure
        {
            return function(SplFileInfo $fileInfo, Migration $_this) use ($destFunc) {

                $destPath   = $destFunc($fileInfo, $_this);
                $originPath = $fileInfo->getRealPath();

                is_dir(dirname($destPath)) || mkdir(dirname($destPath), 0777, true);

                if (is_file($destPath))
                {
                    $_this->logInfo("文件存在，已经跳过：[$destPath]");
                }
                else
                {
                    $_this->logInfo("压缩图片：[$originPath -> $destPath]");
                    $_this->compressImage($originPath, $destPath, false);
                }
            };
        }

        public static function processorVideo(callable $destFunc): \Closure
        {
            return function(SplFileInfo $fileInfo, Migration $_this) use ($destFunc) {

                $destPath   = $destFunc($fileInfo, $_this);
                $originPath = $fileInfo->getRealPath();

                is_dir(dirname($destPath)) || mkdir(dirname($destPath), 0777, true);
                if (is_file($destPath))
                {
                    $_this->logInfo("文件存在，已经跳过：[$destPath]");
                }
                else
                {
                    $_this->logInfo("转换视频：[$originPath -> $destPath]");
                    $_this->compressVideo($originPath, $destPath, false);
                }
            };
        }


        //IMG_20200320_027480.jpg
        public static function processorPhotoHuaWeiCompress(string $dest, bool $isCompress = false): \Closure
        {
            $destFunc = function(SplFileInfo $fileInfo, Migration $_this) use ($dest) {

                $dest = rtrim($dest, '/') . '/';

                // IMG_20200320_027480.jpg
                preg_match('/IMG_(?<year>\d{4})(?<month>\d{2})(?<day>\d{2})_(?<id>\d{6})(?:_\d+)?\.(jpg|png|jpeg)/usi', $fileInfo->getPathname(), $result);

                if (isset($result['day']))
                {
                    $destName = implode('', [
                        $dest,
                        $result['year'] . '-' . $result['month'] . '/',
                        $fileInfo->getFilename(),
                    ]);

                    return $destName;
                }
                else
                {
                    $destName = implode('', [
                        $dest,
                        $fileInfo->getFilename(),
                    ]);
                }

                return $destName;
            };

            if ($isCompress)
            {
                return static::processorImage($destFunc);
            }
            else
            {
                return static::processorCopyFile($destFunc);
            }
        }

        //VID_20250321_124804.mp4
        public static function processorVideoHuaWeiCompress(string $dest, bool $isCompress = false): \Closure
        {
            $destFunc = function(SplFileInfo $fileInfo, Migration $_this) use ($dest) {

                $dest = rtrim($dest, '/') . '/';

                // VID_20250321_124804.mp4
                preg_match('/VID_(?<year>\d{4})(?<month>\d{2})(?<day>\d{2})_(?<id>\d{6})(?:_\d+)?\.(mp4)/usi', $fileInfo->getPathname(), $result);

                if (isset($result['day']))
                {
                    $destName = implode('', [
                        $dest,
                        $result['year'] . '-' . $result['month'] . '/',
                        $fileInfo->getFilename(),
                    ]);

                    return $destName;
                }
                else
                {
                    $destName = implode('', [
                        $dest,
                        $fileInfo->getFilename(),
                    ]);
                }

                return $destName;
            };

            if ($isCompress)
            {
                return static::processorVideo($destFunc);
            }
            else
            {
                return static::processorCopyFile($destFunc);
            }
        }

        // IMG20250910195900.jpg
        // IMG20250910195900_01.jpg
        public static function processorPhotoOppoCompress(string $dest, bool $isCompress = false): \Closure
        {
            $destFunc = function(SplFileInfo $fileInfo, Migration $_this) use ($dest) {

                $dest = rtrim($dest, '/') . '/';

                // IMG20250910195900.jpg
                // IMG20250910195900_01.jpg
                preg_match('/IMG(?<year>\d{4})(?<month>\d{2})(?<day>\d{2})(?<id>\d{6})(?:_\d+)?\.(jpg|png|jpeg)/usi', $fileInfo->getPathname(), $result);

                if (isset($result['day']))
                {
                    $destName = implode('', [
                        $dest,
                        $result['year'] . '-' . $result['month'] . '/',
                        $fileInfo->getFilename(),
                    ]);

                    return $destName;
                }
                else
                {
                    $destName = implode('', [
                        $dest,
                        $fileInfo->getFilename(),
                    ]);
                }

                return $destName;
            };
            if ($isCompress)
            {
                return static::processorImage($destFunc);
            }
            else
            {
                return static::processorCopyFile($destFunc);
            }
        }

        // VID20250910195626.mp4
        public static function processorVideoOppoCompress(string $dest, bool $isCompress = false): \Closure
        {
            $destFunc = function(SplFileInfo $fileInfo, Migration $_this) use ($dest) {

                $dest = rtrim($dest, '/') . '/';

                // VID20250910195626.mp4
                preg_match('/VID(?<year>\d{4})(?<month>\d{2})(?<day>\d{2})(?<id>\d{6})(?:_\d+)?\.(mp4)/usi', $fileInfo->getPathname(), $result);

                if (isset($result['day']))
                {
                    $destName = implode('', [
                        $dest,
                        $result['year'] . '-' . $result['month'] . '/',
                        $fileInfo->getFilename(),
                    ]);

                    return $destName;
                }
                else
                {
                    $destName = implode('', [
                        $dest,
                        $fileInfo->getFilename(),
                    ]);
                }

                return $destName;
            };

            if ($isCompress)
            {
                return static::processorVideo($destFunc);
            }
            else
            {
                return static::processorCopyFile($destFunc);
            }

        }


        // 202108__/IMG_1058.JPG
        public static function processorPhotoIphoneCompress(string $dest, bool $isCompress = false): \Closure
        {
            $destFunc = function(SplFileInfo $fileInfo, Migration $_this) use ($dest) {
                $dest = rtrim($dest, '/') . '/';

                ///var/www/6025/new/coco-mediaMigration/examples/media/iphone/202108__/IMG_1058.JPG
                $originPath = $fileInfo->getRealPath();

                preg_match('/(?<year>20\d{2})(?<month>\d{2})__/usi', $originPath, $result);

                if (isset($result['year']))
                {
                    $year  = $result['year'];
                    $month = $result['month'];
                }
                else
                {
                    $createTime = $fileInfo->getMTime();
                    $year       = date('Y', $createTime);
                    $month      = date('m', $createTime);
                }

                $destName = implode('', [
                    $dest,
                    $year . '-' . $month . '/',
                    $fileInfo->getFilename(),
                ]);

                return $destName;
            };

            if ($isCompress)
            {
                return static::processorImage($destFunc);
            }
            else
            {
                return static::processorCopyFile($destFunc);
            }
        }

        // 202108__/IMG_1262.MOV
        // 202108__/IMG_1262.mp4
        public static function processorVideoIphoneCompress(string $dest, bool $isCompress = false): \Closure
        {
            $destFunc = function(SplFileInfo $fileInfo, Migration $_this) use ($dest) {

                $dest = rtrim($dest, '/') . '/';

                ///var/www/6025/new/coco-mediaMigration/examples/media/iphone/202108__/IMG_1262.MOV
                $originPath = $fileInfo->getRealPath();

                preg_match('/(?<year>20\d{2})(?<month>\d{2})__/usi', $originPath, $result);

                if (isset($result['year']))
                {
                    $year  = $result['year'];
                    $month = $result['month'];
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

                    $nameWithoutExt . '.mp4',
                    //$fileInfo->getFilename(),

                ]);

                return $destName;
            };

            if ($isCompress)
            {
                return static::processorVideo($destFunc);
            }
            else
            {
                return static::processorCopyFile($destFunc);
            }
        }


        public static function processorMirrorDir(string $dest): \Closure
        {
            $destFunc = function(SplFileInfo $fileInfo, Migration $_this) use ($dest) {

                $dest           = rtrim($dest, '/') . '/';
                $originFilePath = $fileInfo->getRealPath();

                // /1.jpg
                $relativePath = strtr($originFilePath, [
                    rtrim($_this->source, '/') => '',
                ]);

                // ./data11/1.jpg
                $destPath = implode('', [
                    $dest,
                    ltrim($relativePath, '/'),
                ]);

                return $destPath;
            };

            return static::processorCopyFile($destFunc);
        }

        public static function processorMirrorVideoCompress(string $dest, bool $isCompress = true): \Closure
        {
            $destFunc = function(SplFileInfo $fileInfo, Migration $_this) use ($dest, $isCompress) {

                $dest           = rtrim($dest, '/') . '/';
                $originFilePath = $fileInfo->getRealPath();

                // /1.jpg
                $relativePath = strtr($originFilePath, [
                    rtrim($_this->source, '/') => '',
                ]);

                // ./data11/1.jpg
                $destPath = implode('', [
                    $dest,
                    ltrim($relativePath, '/'),
                ]);

                return $destPath;
            };

            if ($isCompress)
            {
                return static::processorVideo($destFunc);
            }
            else
            {
                return static::processorCopyFile($destFunc);
            }
        }

        public static function processorMirrorImageCompress(string $dest, bool $isCompress = true): \Closure
        {
            $destFunc = function(SplFileInfo $fileInfo, Migration $_this) use ($dest, $isCompress) {

                $dest           = rtrim($dest, '/') . '/';
                $originFilePath = $fileInfo->getRealPath();

                // /1.jpg
                $relativePath = strtr($originFilePath, [
                    rtrim($_this->source, '/') => '',
                ]);

                // ./data11/1.jpg
                $destPath = implode('', [
                    $dest,
                    ltrim($relativePath, '/'),
                ]);

                return $destPath;
            };

            if ($isCompress)
            {
                return static::processorImage($destFunc);
            }
            else
            {
                return static::processorCopyFile($destFunc);
            }
        }
    }
