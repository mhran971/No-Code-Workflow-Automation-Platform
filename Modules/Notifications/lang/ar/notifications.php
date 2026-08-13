<?php

return [
    'task_assigned' => [
        'title' => 'مهمة جديدة: :title',
        'body' => 'تم تعيين مهمة جديدة لك.',
    ],
    'task_due_soon' => [
        'title' => 'مهمة قريبة الموعد: :title',
        'body' => 'الموعد النهائي: :due_at',
    ],
    'task_overdue' => [
        'title' => 'مهمة متأخرة: :title',
        'body' => 'تجاوزت هذه المهمة الموعد النهائي.',
    ],
    'task_escalated' => [
        'title' => 'تصعيد مهمة: :title',
        'body' => 'تجاوزت المهمة الموكلة إلى :assignee موعد استحقاقها وتم تصعيدها إليك.',
    ],
];
