<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Zufarmarwah\PerformanceGuard\Listeners\QueryListener;

beforeEach(function () {
    $this->listener = new QueryListener;
});

function makeConnection(): Connection
{
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getName')->andReturn('testing');

    return $connection;
}

it('records queries when listening', function () {
    $this->listener->start();

    $connection = makeConnection();
    $event = new QueryExecuted('select * from users', [], 1.5, $connection);

    $this->listener->recordQuery($event);

    expect($this->listener->getQueries())->toHaveCount(1);
    expect($this->listener->getQueryCount())->toBe(1);
});

it('does not record queries when not listening', function () {
    $connection = makeConnection();
    $event = new QueryExecuted('select * from users', [], 1.5, $connection);

    $this->listener->recordQuery($event);

    expect($this->listener->getQueries())->toBeEmpty();
});

it('stops recording after stop is called', function () {
    $this->listener->start();

    $connection = makeConnection();
    $event1 = new QueryExecuted('select * from users', [], 1.5, $connection);
    $this->listener->recordQuery($event1);

    $this->listener->stop();

    $event2 = new QueryExecuted('select * from posts', [], 2.0, $connection);
    $this->listener->recordQuery($event2);

    expect($this->listener->getQueries())->toHaveCount(1);
});

it('detects duplicate queries', function () {
    $this->listener->start();

    $connection = makeConnection();

    $this->listener->recordQuery(new QueryExecuted('select * from users where id = 1', [], 1.0, $connection));
    $this->listener->recordQuery(new QueryExecuted('select * from users where id = 2', [], 1.0, $connection));
    $this->listener->recordQuery(new QueryExecuted('select * from users where id = 3', [], 1.0, $connection));

    expect($this->listener->hasDuplicates())->toBeTrue();
    expect($this->listener->getDuplicates())->toHaveCount(1);
});

it('finds slow queries', function () {
    $this->listener->start();

    $connection = makeConnection();

    $this->listener->recordQuery(new QueryExecuted('select * from users', [], 50.0, $connection));
    $this->listener->recordQuery(new QueryExecuted('select * from posts', [], 500.0, $connection));
    $this->listener->recordQuery(new QueryExecuted('select * from comments', [], 10.0, $connection));

    $slowQueries = $this->listener->getSlowQueries(100.0);

    expect($slowQueries)->toHaveCount(1);
    expect($slowQueries[0]['sql'])->toBe('select * from posts');
});

it('calculates total duration', function () {
    $this->listener->start();

    $connection = makeConnection();

    $this->listener->recordQuery(new QueryExecuted('select 1', [], 10.0, $connection));
    $this->listener->recordQuery(new QueryExecuted('select 2', [], 20.0, $connection));

    expect($this->listener->getTotalDuration())->toBe(30.0);
});

it('resets properly', function () {
    $this->listener->start();

    $connection = makeConnection();
    $this->listener->recordQuery(new QueryExecuted('select 1', [], 1.0, $connection));

    $this->listener->reset();

    expect($this->listener->getQueries())->toBeEmpty();
    expect($this->listener->isListening())->toBeFalse();
});
