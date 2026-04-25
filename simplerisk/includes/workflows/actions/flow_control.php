<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Flow control actions (delay, branch, wait_for_condition) are handled
// directly by the executor in executor.php. This file is intentionally
// minimal — it exists as a placeholder so the action catalog can reference
// 'delay', 'branch', and 'wait_for_condition' without them being
// dispatched through execute_workflow_action().
//
// If any utility functions needed by the executor for flow control are
// added in the future, they belong here.
