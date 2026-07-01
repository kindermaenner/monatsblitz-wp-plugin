<?php

declare(strict_types=1);

use Monatsblitz\Output\RecreatePostsHandler;

it('returns db_error when tournaments cannot be loaded', function () {
    global $wpdb;

    $wpdb->get_results_queue = [null];

    $handler = new RecreatePostsHandler();
    $result = $handler->handle();

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->code)->toBe('db_error');
});

it('recreates all valid tournament posts successfully and skips invalid ids', function () {
    global $wpdb;

    $wpdb->results = [
        ['id' => 11],
        ['id' => 0],
        ['id' => ''],
        ['id' => 12],
    ];

    $collector = (object)['calls' => []];

    $handler = new class($collector) extends RecreatePostsHandler {
        private array $resultsByTournamentId = [];
        private object $collector;

        public function __construct(object $collector)
        {
            $this->collector = $collector;
        }

        public function setResultsByTournamentId(array $resultsByTournamentId): void
        {
            $this->resultsByTournamentId = $resultsByTournamentId;
        }

        protected function createFinalizeHandler(): object
        {
            return new class($this->collector, $this->resultsByTournamentId) {
                private object $collector;
                private array $resultsByTournamentId;

                public function __construct(object $collector, array $resultsByTournamentId)
                {
                    $this->collector = $collector;
                    $this->resultsByTournamentId = $resultsByTournamentId;
                }

                public function handle($request)
                {
                    $params = $request->get_json_params();
                    $tournamentId = (int)($params['tournament_id'] ?? 0);
                    $this->collector->calls[] = $tournamentId;

                    return $this->resultsByTournamentId[$tournamentId] ?? ['success' => true];
                }
            };
        }
    };

    $handler->setResultsByTournamentId([
        11 => ['success' => true],
        12 => ['success' => true],
    ]);

    $result = $handler->handle();

    expect($collector->calls)->toBe([11, 12]);
    expect($result)->toBeArray()
        ->and($result['success'])->toBeTrue()
        ->and($result['processed'])->toBe(2)
        ->and($result['succeeded'])->toBe(2)
        ->and($result['failed'])->toBe(0)
        ->and($result['errors'])->toBe([]);
});

it('collects errors per tournament when finalize fails for some tournaments', function () {
    global $wpdb;

    $wpdb->results = [
        ['id' => 21],
        ['id' => 22],
        ['id' => 23],
    ];

    $collector = (object)['calls' => []];

    $handler = new class($collector) extends RecreatePostsHandler {
        private array $resultsByTournamentId = [];
        private object $collector;

        public function __construct(object $collector)
        {
            $this->collector = $collector;
        }

        public function setResultsByTournamentId(array $resultsByTournamentId): void
        {
            $this->resultsByTournamentId = $resultsByTournamentId;
        }

        protected function createFinalizeHandler(): object
        {
            return new class($this->collector, $this->resultsByTournamentId) {
                private object $collector;
                private array $resultsByTournamentId;

                public function __construct(object $collector, array $resultsByTournamentId)
                {
                    $this->collector = $collector;
                    $this->resultsByTournamentId = $resultsByTournamentId;
                }

                public function handle($request)
                {
                    $params = $request->get_json_params();
                    $tournamentId = (int)($params['tournament_id'] ?? 0);
                    $this->collector->calls[] = $tournamentId;

                    return $this->resultsByTournamentId[$tournamentId] ?? ['success' => true];
                }
            };
        }
    };

    $handler->setResultsByTournamentId([
        21 => ['success' => true],
        22 => new WP_Error('no_results', 'Keine Ergebnisse vorhanden', ['status' => 400]),
        23 => new class('post_error', 'Fehler beim Anlegen des Beitrags', ['status' => 500]) extends WP_Error {
            public function get_error_code()
            {
                return $this->code;
            }

            public function get_error_message()
            {
                return $this->message;
            }

            public function get_error_data($key = '')
            {
                return $this->data[$key] ?? null;
            }
        },
    ]);

    $result = $handler->handle();

    expect($collector->calls)->toBe([21, 22, 23]);
    expect($result['success'])->toBeFalse();
    expect($result['processed'])->toBe(3);
    expect($result['succeeded'])->toBe(1);
    expect($result['failed'])->toBe(2);
    expect($result['errors'])->toHaveCount(2);

    expect($result['errors'][0])->toBe([
        'tournament_id' => 22,
        'code' => 'no_results',
        'message' => 'Keine Ergebnisse vorhanden',
        'status' => 400,
    ]);

    expect($result['errors'][1])->toBe([
        'tournament_id' => 23,
        'code' => 'post_error',
        'message' => 'Fehler beim Anlegen des Beitrags',
        'status' => 500,
    ]);
});
