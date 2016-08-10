<?php

namespace Instante\Bootstrap;

use Instante\Utils\TEnumerator;

class ConsoleDebugModeEnum
{
    use TEnumerator;

    const ENABLED = TRUE;
    const DISABLED = FALSE;
    const AUTO = NULL;
}
