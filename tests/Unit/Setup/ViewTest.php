<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Tests\Unit\Setup;

use OxidEsales\EshopCommunity\Setup\View;
use OxidEsales\EshopCommunity\Setup\Session as SetupSession;

require_once getShopBasePath() . '/Setup/functions.php';


/**
 * view tests
 */
class ViewTest extends \OxidTestCase
{
    /**
     * Testing view::getTitle()
     */
    public function testGetTitle()
    {
        $oSetupView = $this->getMock(\OxidEsales\EshopCommunity\Setup\View::class, array("getText"));
        $oSetupView->expects($this->once())->method("getText")->with($this->equalTo(null), $this->equalTo(false))->will($this->returnValue("getText"));
        $this->assertEquals("getText", $oSetupView->getTitle());
    }

    /**
     * Testing view::setTitle()
     */
    public function testSetTitle()
    {
        $oSetupView = $this->getMock(\OxidEsales\EshopCommunity\Setup\View::class, array("getText"));
        $oSetupView->expects($this->once())->method("getText")->with($this->equalTo("testTitle"));
        $oSetupView->setTitle("testTitle");
        $oSetupView->getTitle();
    }

    /**
     * Testing view::getMessages()
     */
    public function testGetMessages()
    {
        $oSetupView = new View();
        $this->assertEquals(array(), $oSetupView->getMessages());
    }

    /**
     * Testing view::setMessage()
     */
    public function testSetMessage()
    {
        $oSetupView = new View();
        $oSetupView->setMessage("msg1");
        $this->assertEquals(array("msg1"), $oSetupView->getMessages());
        $oSetupView->setMessage("msg2");
        $this->assertEquals(array("msg1", "msg2"), $oSetupView->getMessages());
        $oSetupView->setMessage("msg3", true);
        $this->assertEquals(array("msg3"), $oSetupView->getMessages());
    }

    /**
     * Testing view::getText()
     */
    public function testGetText()
    {
        $oInst = $this->getMock("Language", array("getText"));
        $oInst->expects($this->once())->method("getText")->with($this->equalTo("testId"));

        $oSetupView = $this->getMock(\OxidEsales\EshopCommunity\Setup\View::class, array("getInstance"));
        $oSetupView->expects($this->once())->method("getInstance")->with($this->equalTo("Language"))->will($this->returnValue($oInst));
        $oSetupView->getText("testId", false);
    }

    /**
     * Testing view::setViewParam() and view::getViewParam()
     */
    public function testSetViewParamGetViewParam()
    {
        $oSetupView = new View();
        $oSetupView->setViewParam("testParamName", "testParamValue");
        $this->assertEquals("testParamValue", $oSetupView->getViewParam("testParamName"));
    }

    /**
     * Testing view::getSetupStep()
     */
    public function testGetSetupStep()
    {
        $oInst = $this->getMock("Setup", array("getStep"));
        $oInst->expects($this->once())->method("getStep")->with($this->equalTo("testStepId"));

        $oSetupView = $this->getMock(\OxidEsales\EshopCommunity\Setup\View::class, array("getInstance"));
        $oSetupView->expects($this->once())->method("getInstance")->with($this->equalTo("Setup"))->will($this->returnValue($oInst));
        $oSetupView->getSetupStep("testStepId", false);
    }

    /**
     * Testing view::getNextSetupStep()
     */
    public function testGetNextSetupStep()
    {
        $oInst = $this->getMock("Setup", array("getNextStep"));
        $oInst->expects($this->once())->method("getNextStep");

        $oSetupView = $this->getMock(\OxidEsales\EshopCommunity\Setup\View::class, array("getInstance"));
        $oSetupView->expects($this->once())->method("getInstance")->with($this->equalTo("Setup"))->will($this->returnValue($oInst));
        $oSetupView->getNextSetupStep();
    }

    /**
     * Testing view::getNextSetupStep()
     */
    public function testGetCurrentSetupStep()
    {
        $oInst = $this->getMock("Setup", array("getCurrentStep"));
        $oInst->expects($this->once())->method("getCurrentStep");

        $oSetupView = $this->getMock(\OxidEsales\EshopCommunity\Setup\View::class, array("getInstance"));
        $oSetupView->expects($this->once())->method("getInstance")->with($this->equalTo("Setup"))->will($this->returnValue($oInst));
        $oSetupView->getCurrentSetupStep();
    }

    /**
     * Testing view::getSetupSteps()
     */
    public function testGetSetupSteps()
    {
        $oInst = $this->getMock("Setup", array("getSteps"));
        $oInst->expects($this->once())->method("getSteps");

        $oSetupView = $this->getMock(\OxidEsales\EshopCommunity\Setup\View::class, array("getInstance"));
        $oSetupView->expects($this->once())->method("getInstance")->with($this->equalTo("Setup"))->will($this->returnValue($oInst));
        $oSetupView->getSetupSteps();
    }

    /**
     * Testing view::isDeletedSetup()
     */
    public function testIsDeletedsetup(): void
    {
        $sPath = getShopBasePath();

        $aDB = ["dbiDemoData" => 0];
        $blDelSetupDir = ["blDelSetupDir" => true];

        $oInst2 = $this->getMock("Utilities", array("removeDir"));
        $oInst2
            ->method('removeDir')
            ->withConsecutive(
                [$sPath . "out/pictures/generated", true],
                [$sPath . "out/pictures/master", true],
                [$sPath . "Setup", true]
            )
            ->willReturnOnConsecutiveCalls(
                true,
                true,
                true
            );

        $oSetupView = $this->getMock(\OxidEsales\EshopCommunity\Setup\View::class, array("getInstance"));
        $oSetupView->expects($this->atLeastOnce())->method("getInstance")->with($this->equalTo("Utilities"))->will($this->returnValue($oInst2));
        $this->assertTrue($oSetupView->isDeletedSetup($blDelSetupDir, $aDB));
    }

    /**
     * Testing view::getReqInfoUrl()
     */
    public function testGetReqInfoUrl()
    {
        $sUrl = "https://docs.oxid-esales.com/eshop/en/latest/installation/new-installation/server-and-system-requirements.html";

        $oSetupView = new View();
        $this->assertEquals($sUrl . "#php", $oSetupView->getReqInfoUrl("php_version", false));
        $this->assertEquals($sUrl . "#web-server", $oSetupView->getReqInfoUrl("mod_rewrite", false));
        $this->assertEquals($sUrl . "#database", $oSetupView->getReqInfoUrl("mysql_version", false));
        $this->assertEquals($sUrl, $oSetupView->getReqInfoUrl("none", false));
    }

    /**
     * Testing view::getSid()
     */
    public function testGetSid()
    {
        $oInst = $this->getMock(SetupSession::class, array("getSid"), array(), '', false);
        $oInst->expects($this->once())->method("getSid")->will($this->returnValue("testSid"));

        $oSetupView = $this->getMock(\OxidEsales\EshopCommunity\Setup\View::class, array("getInstance"));
        $oSetupView->expects($this->once())->method("getInstance")->with($this->equalTo("Session"))->will($this->returnValue($oInst));
        $this->assertEquals("testSid", $oSetupView->getSid(false));
    }
}
