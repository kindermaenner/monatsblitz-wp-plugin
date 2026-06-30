<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

class MainServiceFakeRequest
{
	public function __construct(private array $params)
	{
	}

	public function get_json_params(): array
	{
		return $this->params;
	}
}