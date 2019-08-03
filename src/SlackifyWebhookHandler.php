<?php


namespace Isofman\LaravelSlackify;

use Illuminate\Support\Str;
use Monolog\Handler\Curl;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class SlackifyWebhookHandler extends AbstractProcessingHandler
{
    private $config;

    public function __construct($config, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->config = $config;
    }

    private function convertPath($path)
    {
        $os = strtolower(PHP_OS);
        return Str::contains($os, ['linux', 'unix']) ? str_replace('\\', '/', $path) : $path;
    }

    private function cleanTrace(&$record)
    {
        $trace = $record['context']['exception']['trace'];
        $fileName = $record['context']['exception']['file'];

        if(Str::contains($fileName, $this->convertPath('\app\\'))) {
            $fileName = explode($this->convertPath('\app\\'), $fileName)[1];
        }

        # Get trace from app and libs
        $niceTrace = array_filter($trace, function($item) {
            return Str::contains($item, $this->convertPath('\app\\'))
                || !Str::contains($item, $this->convertPath('\vendor\\laravel\\'));
        });

        # Get trace from framework
        if(count($niceTrace) === 0) {
            $niceTrace = array_slice($trace, 0, 3);
        }

        $niceTrace = array_map(function($file) {
            foreach (['\vendor\\laravel\\', '\app\\', '\public\\', '\vendor\\'] as $path) {
                $path = $this->convertPath($path);
                if(Str::contains($file, $path)) {
                    return explode($path, $file)[1];
                }
            }

            return $file;
        }, $niceTrace);

        $record['context']['exception']['trace'] = $niceTrace;
        $record['context']['exception']['file'] = $fileName;
    }

    private function beautifyException(&$record)
    {
        $context = $record['context'];
        $context = json_encode($context, JSON_PRETTY_PRINT);
        $record['context'] = $context;
    }

    private function messageWithEnv($msg)
    {
        return ucwords(config('app.env')) .': '. $msg;
    }

    public function getSlackData(array $record)
    {
        $message = $this->messageWithEnv($record['message']);
        $level = $record['level_name'];
        $isException = false;

        if(!empty($record['context']['exception'])) {
            $isException = true;
            $this->cleanTrace($record);
            $this->beautifyException($record);
        }

        $fields = [
            ['title' => 'Level', 'value' => $level, 'short' => false],
        ];

        if($isException) {
            $fields[] = [
                'title' => 'Exception',
                'value' => '```' . $record['context'] . '```',
                'short' => false,
            ];
        }

        return [
            'username' => 'Laravel Log',
            'attachments' => [
                [
                    'fallback' => $message,
                    'text' => $message,
                    'color' => 'danger',
                    'fields' => $fields,
                    'mrkdwn_in' =>
                        [
                            0 => 'fields',
                        ],
                    'ts' => 1553487764,
                    'title' => ucwords(config('app.env')),
                ]
            ],
            'icon_emoji' => ':boom:',
        ];
    }

    protected function write(array $record)
    {
        try {
            $record = $this->getSlackData($record['formatted']);
            $postData = $record;
            $postString = json_encode($postData);

            $ch = curl_init();
            $options = array(
                CURLOPT_URL => $this->config['url'],
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array('Content-type: application/json'),
                CURLOPT_POSTFIELDS => $postString
            );
            if (defined('CURLOPT_SAFE_UPLOAD')) {
                $options[CURLOPT_SAFE_UPLOAD] = true;
            }

            curl_setopt_array($ch, $options);

            Curl\Util::execute($ch);
        } catch (\Exception $exception) { }
    }
}
