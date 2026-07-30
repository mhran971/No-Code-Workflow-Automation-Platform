<?php

namespace Modules\Workflows\Enums;

enum WaitType: string
{
    case TaskSla = 'task_sla';
    case MergeTimeout = 'merge_timeout';
    case SubWorkflow = 'subworkflow';
    case DynamicFlowDesign = 'dynamic_flow_design';
}
