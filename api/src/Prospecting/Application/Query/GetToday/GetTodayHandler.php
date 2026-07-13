<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Query\GetToday;

use App\Prospecting\Application\ReadModel\TodayBoard;
use App\Prospecting\Application\ReadModel\TodayView;
use App\Shared\Application\Query\QueryHandler;

final class GetTodayHandler implements QueryHandler
{
    public function __construct(private readonly TodayBoard $board)
    {
    }

    public function __invoke(GetToday $query): TodayView
    {
        return $this->board->view();
    }
}
