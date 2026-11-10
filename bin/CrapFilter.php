<?php

declare(strict_types=1);

namespace Tooling;

final class CrapFilter
{
    public static function classOf(string $path): ?string
    {
        if (1 !== preg_match('#^src/(.+)\.php$#', $path, $match)) {
            return null;
        }

        return 'App\\'.str_replace('/', '\\', $match[1]);
    }

    /**
     * @param list<string> $changedFiles
     *
     * @return list<array{method: string, crap: float}>
     */
    public static function violations(string $crap4jXml, float $max, array $changedFiles): array
    {
        $classes = array_flip(array_filter(array_map(self::classOf(...), $changedFiles)));
        $report = @simplexml_load_string($crap4jXml);
        if (false === $report) {
            throw new \InvalidArgumentException('Unreadable crap4j report');
        }

        $violations = [];
        foreach ($report->xpath('//method') ?: [] as $method) {
            $class = (string) $method->className;
            if (isset($classes[$class]) && (float) $method->crap > $max) {
                $violations[] = ['method' => $class.'::'.$method->methodName, 'crap' => (float) $method->crap];
            }
        }

        return $violations;
    }
}
