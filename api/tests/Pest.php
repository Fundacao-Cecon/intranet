<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Os testes da API (cenários portados do legado, ver docs/matriz-de-paridade.md) ficam em
| tests/Feature/Api e usam um banco em memória com a baseline aplicada.
*/
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature/Api');
