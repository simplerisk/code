<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Attributes as OA;

// The attributes below describe the API contract statically — they must load without
// a live SimpleRisk runtime so offline tooling (the GitHub Actions Postman
// collection generator, third-party OpenAPI consumers running swagger-php from
// the command line, etc.) can produce a spec without bootstrapping the database
// layer. The placeholder URL on OA\Server is overwritten with the customer's
// live SIMPLERISK_BASE_URL at runtime by simplerisk/api/v2/documentation/index.php
// so the in-product Swagger UI continues to show the live URL pre-filled.

#[OA\OpenApi(
    info: new OA\Info(
        title: 'SimpleRisk API',
        description: 'This is the documentation for the SimpleRisk API',
        version: '2.0.0',
        contact: new OA\Contact(email: 'support@simplerisk.com'),
        license: new OA\License(
            name: 'Mozilla Public License Version 2.0',
            url: 'https://www.mozilla.org/en-US/MPL/2.0/',
        ),
    ),
    servers: [new OA\Server(url: 'https://your-simplerisk-instance.example.com/api/v2', description: 'SimpleRisk')],
    externalDocs: new OA\ExternalDocumentation(
        description: 'SimpleRisk Support Portal',
        url: 'https://support.simplerisk.com',
    ),
    tags: [
        new OA\Tag(name: 'admin', description: 'Administrator Operations'),
        new OA\Tag(name: 'user', description: 'User Operations'),
        new OA\Tag(name: 'asset_crud', description: 'Asset Management (CRUD)'),
        new OA\Tag(name: 'asset', description: 'Asset Management (Legacy)'),
        new OA\Tag(name: 'risk_crud', description: 'Risk Management (CRUD)'),
        new OA\Tag(name: 'risk', description: 'Risk Management (Legacy)'),
        new OA\Tag(name: 'governance_crud', description: 'Governance (CRUD)'),
        new OA\Tag(name: 'governance', description: 'Governance (Legacy)'),
        new OA\Tag(name: 'Artificial Intelligence', description: 'Artificial Intelligence Operations'),
        new OA\Tag(name: 'assessment', description: 'Risk Assessment Extra'),
        new OA\Tag(name: 'compliance_crud', description: 'Compliance (CRUD)'),
        new OA\Tag(name: 'compliance', description: 'Compliance (Legacy)'),
        new OA\Tag(name: 'reports', description: 'Reporting Operations'),
        new OA\Tag(name: 'risk_formula', description: 'Risk Formula Operations'),
        new OA\Tag(name: 'ui', description: 'User Interface Operations'),
        new OA\Tag(
            name: 'notifications',
            description: 'In-app notification bell - read/unread/trash management for the current user.',
        ),
        new OA\Tag(name: 'self_assessments', description: 'Core SCF-based self-assessments'),
        new OA\Tag(
            name: 'need_explode_for_arrays',
            description: 'Technical tag for marking a request for the Swagger schema generating logic to add encoding/explode definitions so array parameters in the requests are sent in a format the API expects them.',
        ),
    ],
)]
class OpenApiGeneral {}

?>