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
            if (is_a($record['context']['exception'], 'ExceptionWithMetaContext')) {
                $formatted['context']['exception']['meta'] =
                    $record['context']['exception']->getContext();
            }
        } catch (\Exception $exception) {
        }
        return $formatted;
    }
}
