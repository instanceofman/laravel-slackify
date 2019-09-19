<?php


namespace Isofman\LaravelSlackify;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\NormalizerFormatter;

class SlackFormatter extends NormalizerFormatter implements FormatterInterface
{
    public function format(array $record)
    {
        $formatted = parent::format($record);

        try {
            if(method_exists($record['context']['exception'], 'getMeta')) {
                $formatted['context']['exception']['meta'] =
                    $record['context']['exception']->getMeta();
            } else if(method_exists($record['context']['exception'], 'getErrorMeta')) {
                $formatted['context']['exception']['meta'] =
                    $record['context']['exception']->getErrorMeta();
            } else if(method_exists($record['context']['exception'], 'getContext')) {
                $formatted['context']['exception']['meta'] =
                    $record['context']['exception']->getContext();
            }
        } catch (\Exception $exception) {}

        return $formatted;
    }
}
