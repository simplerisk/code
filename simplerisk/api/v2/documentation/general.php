<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

use OpenApi\Annotations as OA;

define('SIMPLERISK_BASE_URL', get_setting("simplerisk_base_url"));
define('API_PATH', "/api/v2");
define('SIMPLERISK_API_URL', SIMPLERISK_BASE_URL.API_PATH);

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
*     url=SIMPLERISK_API_URL,
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
*     name="asset",
*     description="Asset Operations",
*   ),
*   @OA\Tag(
*     name="risk",
*     description="Risk Operations",
*   ),
*   @OA\Tag(
*     name="governance",
*     description="Governance Operations",
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
*     name="need_explode_for_arrays",
*     description="Technical tag for marking a request for the Swagger schema generating logic to add encoding/explode definitions so array parameters in the requests are sent in a format the API expects them.",
*   ),
* )
*/

class OpenApiGeneral {}

?>