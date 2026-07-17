<?php

namespace Modules\Workflows\app\Enums;

enum ResultKind
{
    case Proceed;    // succeed and follow the given outgoing edges
    case Branch;     // succeed and follow exactly one selected edge
    case Wait;       // park the token on a durable wait
    case Fail;       // node failed (maybe retryable)
    case Terminate;  // consume this token (branch end)
    case Noop;       // do nothing (e.g. a join arrival that is not the last)
}
