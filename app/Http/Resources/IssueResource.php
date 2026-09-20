<?php

namespace App\Http\Resources;

use App\Models\Issue;
use App\Services\Workflow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $data['overdue'] = ! in_array($this->status, Issue::FINAL, true) && $this->due_date?->lt(today());
        $data['spent'] = (float) ($this->worklogs_sum_hours ?? 0);
        $data['allowed_transitions'] = app(Workflow::class)->allowed($request->user(), $this->resource);
        $data['can_edit'] = $request->user()->manages($this->project) || ($request->user()->id === $this->reporter_id && $request->user()->hasPermission('create'));
        $data['can_estimate'] = $request->user()->manages($this->project) || ($request->user()->id === $this->assignee_id && $request->user()->hasPermission('estimate'));
        $data['can_assign'] = $request->user()->manages($this->project) || $request->user()->hasPermission('assign');

        return $data;
    }
}
