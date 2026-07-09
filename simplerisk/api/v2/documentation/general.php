<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// @phan-suppress-next-line PhanUnreferencedUseNormal -- OA alias used in PHPDoc @OA annotations
use OpenApi\Annotations as OA;

// Annotations below describe the API contract statically — they must parse without
// a live SimpleRisk runtime so offline tooling (the GitHub Actions Postman
// collection generator, third-party OpenAPI consumers running swagger-php from
// the command line, etc.) can produce a spec without bootstrapping the database
// layer. The placeholder URL on @OA\Server is overwritten with the customer's
// live SIMPLERISK_BASE_URL at runtime by simplerisk/api/v2/documentation/index.php
// so the in-product Swagger UI continues to show the live URL pre-filled.

/**
* @OA\OpenApi(
*   @OA\Info(
*     title="SimpleRisk API",
*     description="This is the documentation for the SimpleRisk API",
*     version="2.0.0",
*     @OA\Contact(
*       email="support@simplerisk.com",
*     ),
*     @OA\License(
*       name="Mozilla Public License Version 2.0",
*       url="https://www.mozilla.org/en-US/MPL/2.0/",
*     ),
*   ),
*   @OA\Server(
*     url="https://your-simplerisk-instance.example.com/api/v2",
*     description="SimpleRisk",
*   ),
*   @OA\ExternalDocumentation(
*     description="SimpleRisk Support Portal",
*     url="https://support.simplerisk.com",
*   ),
*   @OA\Tag(
*     name="admin",
*     description="Administrator Operations",
*   ),
*   @OA\Tag(
*     name="user",
*     description="User Operations",
*   ),
*   @OA\Tag(
*     name="asset_crud",
*     description="Asset Management (CRUD)",
*   ),
*   @OA\Tag(
*     name="asset",
*     description="Asset Management (Legacy)",
*   ),
*   @OA\Tag(
*     name="risk_crud",
*     description="Risk Management (CRUD)",
*   ),
*   @OA\Tag(
*     name="risk",
*     description="Risk Management (Legacy)",
*   ),
*   @OA\Tag(
*     name="governance_crud",
*     description="Governance (CRUD)",
*   ),
*   @OA\Tag(
*     name="governance",
*     description="Governance (Legacy)",
*   ),
*   @OA\Tag(
*     name="Artificial Intelligence",
*     description="Artificial Intelligence Operations",
*  ),
*  @OA\Tag(
*      name="assessment",
*      description="Risk Assessment Extra",
*    ),
*   @OA\Tag(
*     name="compliance_crud",
*     description="Compliance (CRUD)",
*   ),
*   @OA\Tag(
*     name="compliance",
*     description="Compliance (Legacy)",
*   ),
*   @OA\Tag(
*     name="reports",
*     description="Reporting Operations",
*   ),
*   @OA\Tag(
*     name="risk_formula",
*     description="Risk Formula Operations",
*   ),
*   @OA\Tag(
*     name="ui",
*     description="User Interface Operations",
*   ),
*   @OA\Tag(
*     name="notifications",
*     description="In-app notification bell - read/unread/trash management for the current user.",
*   ),
*   @OA\Tag(
*     name="need_explode_for_arrays",
*     description="Technical tag for marking a request for the Swagger schema generating logic to add encoding/explode definitions so array parameters in the requests are sent in a format the API expects them.",
*   ),
* )
*/

class OpenApiGeneral {}

?>