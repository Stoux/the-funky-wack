<?php

namespace App\Enums;

enum TrackAliasStatus: string
{
    /** Linked or created automatically, waiting for a look in the admin */
    case Auto = 'auto';

    /** Looks like an unknown track ('?', 'ID', no artist), not linked to anything */
    case Unknown = 'unknown';

    /** Checked by an admin. Without tracks it's a confirmed unknown */
    case Reviewed = 'reviewed';
}
