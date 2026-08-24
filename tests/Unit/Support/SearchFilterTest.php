<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SearchFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SearchFilterTest extends TestCase
{
    #[DataProvider('searchParameters')]
    public function test_it_resolves_supported_search_parameter_shapes(array $params, string $expected): void
    {
        $this->assertSame($expected, SearchFilter::fromArray($params));
    }

    public static function searchParameters(): array
    {
        return [
            'canonical search' => [['search' => 'health'], 'health'],
            'legacy searchQueries' => [['searchQueries' => 'donor'], 'donor'],
            'nested filter search' => [['filter' => ['search' => 'campaign']], 'campaign'],
            'dot filter search' => [['filter.search' => 'volunteer'], 'volunteer'],
            'flat filter search' => [['filter_search' => 'organization'], 'organization'],
            'trims whitespace' => [['search' => '  article  '], 'article'],
            'ignores all sentinel' => [['search' => 'all', 'searchQueries' => 'usable'], 'usable'],
            'empty search' => [[], ''],
        ];
    }
}
