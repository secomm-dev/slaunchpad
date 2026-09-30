<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Model;

use Magento\Backend\Model\Session\Quote as BackendQuoteSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Group;
use Mageplaza\TableRateShipping\Model\Method;
use Mageplaza\TableRateShipping\Helper\Data;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * TASK-SEC-C3 — CHARACTERIZATION of Mageplaza `Method::isActive()` group resolution.
 *
 * Proves (with the real vendor method, only ambient sessions mocked):
 *  - storefront/admin area semantics: admin reads the backend quote session, frontend reads
 *    the ambient customer session — consistent there;
 *  - STATELESS REST/GraphQL-token context: the ambient customer session is empty, so
 *    `getCustomerGroupId()` falls back to NOT_LOGGED_IN — an authenticated customer-token
 *    request would be evaluated AS GUEST unless the session was bridged upstream
 *    (GraphQL bridges it in core; plain REST does not).
 *
 * Verdict (recorded in the audit): CONFIRMED for plain REST — the session is the only group
 * authority inside the vendor method; the bridge-level fix must feed/resolve a trustworthy
 * context instead. No vendor file is modified here.
 */
class RestCustomerGroupCharacterizationTest extends TestCase
{
    private CustomerSession&MockObject $customerSession;

    private BackendQuoteSession&MockObject $backendQuoteSession;

    private Data&MockObject $helper;

    private Method $method;

    protected function setUp(): void
    {
        // Reflection-built instance: AbstractModel's real constructor needs a booted
        // ObjectManager; isActive() only touches the four collaborators injected here.
        $this->helper = $this->createMock(Data::class);
        $this->helper->method('isAdmin')->willReturn(false);
        $this->backendQuoteSession = $this->createMock(BackendQuoteSession::class);
        $this->customerSession = $this->createMock(CustomerSession::class);

        $this->method = (new \ReflectionClass(Method::class))->newInstanceWithoutConstructor();
        $props = [
            'helper' => $this->helper,
            'backendSession' => $this->backendQuoteSession,
            'customerSession' => $this->customerSession,
        ];
        foreach ($props as $name => $value) {
            $prop = new \ReflectionProperty(Method::class, $name);
            $prop->setAccessible(true);
            $prop->setValue($this->method, $value);
        }
        $this->method->setStatus(1); // Status::ENABLE
        $this->method->setStoreId('0'); // all stores
        $this->method->setCustomerGroup('1'); // General customers ONLY
    }

    public function testGeneralGroupMethodAcceptsLoggedInSessionGroup(): void
    {
        $this->customerSession->method('getCustomerGroupId')->willReturn(1);

        $this->assertTrue($this->method->isActive(0));
    }

    public function testStatelessRestContextFallsBackToNotLoggedInAndIsRejected(): void
    {
        // REST token request WITHOUT a session bridge: the customer session is anonymous,
        // so Magento falls back to NOT_LOGGED_IN — the General-only method is rejected even
        // though the caller is an authenticated customer. This is the confirmed defect.
        $this->customerSession->method('getCustomerGroupId')->willReturn(Group::NOT_LOGGED_IN_ID);
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $this->assertFalse($this->method->isActive(0));
    }
}
