<?php
// site/public/index.php — front controller. Boots core, hands off to the
// router. Router looks up REQUEST_URI in the pages table and either
// requires a file-based page from site/pages/ or renders a data-driven
// page (Phase 8 implements that path).

declare(strict_types=1);

require __DIR__ . '/../../core/lib/bootstrap.php';

route_request();
