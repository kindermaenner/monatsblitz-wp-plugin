<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

use Monatsblitz\Service\BlitzModeService;

class FinalizeTournamentHandler
{
    public function handle($request)
    {
        $service = new FinalizeTournamentService();
        return $service->handle($request);
    }

    // Compatibility wrappers used by existing tests and callers.
    public function normalize_items($request)
    {
        return RequestNormalizer::normalizeItems($request);
    }

    public static function normalize_result_cell(string $result, bool $invert): string
    {
        return TournamentContentBuilder::normalizeResultCell($result, $invert);
    }

    public static function normalize_string_list($input)
    {
        return RequestNormalizer::normalizeStringList($input);
    }
}
