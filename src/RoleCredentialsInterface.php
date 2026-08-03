<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Role-credentials capability.
 *
 * A single CNR-class capability segregated off the shared client contract:
 * composing a role login ("<account>:<role>") from an account id, a role id and
 * the role user's own password. This is NOT part of the universal client surface
 * because it depends on a role separator that only the CNR platform defines
 * (`":"`); flat platforms such as IBS/Moniker have no separator, so inheriting
 * the behaviour there would silently forge a garbage `<uid><role>` login rather
 * than reject it. Consumers holding the shared {@see AbstractClient} type narrow
 * via `instanceof RoleCredentialsInterface` before calling it. Mirrors the
 * {@see ExtendedResponseInterface} precedent on the Response side.
 *
 * @psalm-api
 * @package CNIC
 */
interface RoleCredentialsInterface
{
    /**
     * Set Role Credentials to be used for API communication
     * @param string $accountId empty string resets it
     * @param string $roleId empty string logs in as the account itself, without a role
     * @param string $password the role user's own password; empty string resets it
     */
    public function setRoleCredentials(string $accountId = "", string $roleId = "", string $password = ""): static;
}
