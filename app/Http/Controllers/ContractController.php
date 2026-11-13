<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ContractIndexRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Services\ContractService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractService $contracts,
    ) {}

    public function index(ContractIndexRequest $request): AnonymousResourceCollection
    {
        return ContractResource::collection(
            $this->contracts->search($request->toSearch()),
        );
    }

    public function show(Contract $contract): ContractResource
    {
        return ContractResource::make($contract->load(['company', 'region']));
    }
}
