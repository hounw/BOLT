# Core Upgrade Recipes

Independent private company repositories adopted from BOLT are expected to diverge. Core updates should therefore ship as small, reviewable conventional commits plus a short recipe describing prerequisites, migrations, copied files, API changes, verification, and rollback considerations.

No upgrade recipe is required for the initial `v1.0.0` release. Add one Markdown file per later core change using a sortable name such as `2026-08-webhook-hardening.md`; customized downstreams can review and cherry-pick the referenced commit instead of adopting a runtime plugin system.

`USING-BOLT.md` defines how a downstream agent evaluates, ports, verifies, and records these upgrades.
