<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Application\Controller;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\UtilsObject;
use OxidEsales\Eshop\Application\Model\BasketContentMarkGenerator;

/**
 * Order manager. Arranges user ordering data, checks/validates
 * it, on success stores ordering data to DB.
 */
class OrderController extends \OxidEsales\Eshop\Application\Controller\FrontendController
{
    /**
     * Payment object
     *
     * @var object
     */
    protected $_oPayment = null;

    /**
     * Active basket
     *
     * @var \OxidEsales\Eshop\Application\Model\Basket
     */
    protected $_oBasket = null;

    /**
     * Order user remark
     *
     * @var string
     */
    protected $_sOrderRemark = null;

    /**
     * Basket articlelist
     *
     * @var object
     */
    protected $_oBasketArtList = null;

    /**
     * Remote Address
     *
     * @var string
     */
    protected $_sRemoteAddress = null;

    /**
     * Delivery address
     *
     * @var \OxidEsales\Eshop\Application\Model\Address
     */
    protected $_oDelAddress = null;

    /**
     * Shipping set
     *
     * @var object
     */
    protected $_oShipSet = null;

    /**
     * Config option "blConfirmAGB"
     *
     * @var bool
     */
    protected $_blConfirmAGB = null;

    /**
     * Config option "blShowOrderButtonOnTop"
     *
     * @var bool
     */
    protected $_blShowOrderButtonOnTop = null;

    /**
     * Boolean of option "blConfirmAGB" error
     *
     * @var bool
     */
    protected $_blConfirmAGBError = null;

    /**
     * Current class template name.
     *
     * @var string
     */
    protected $_sThisTemplate = 'page/checkout/order.tpl';

    /**
     * Order step marker
     *
     * @var bool
     */
    protected $_blIsOrderStep = true;

    /**
     * Count of wrapping + cards options
     */
    protected $_iWrapCnt = null;

    /**
     * Loads basket \OxidEsales\Eshop\Core\Session::getBasket(), sets $this->oBasket->blCalcNeeded = true to
     * recalculate, sets back basket to session \OxidEsales\Eshop\Core\Session::setBasket(), executes
     * parent::init().
     */
    public function init()
    {
        // disabling performance control variable
        \OxidEsales\Eshop\Core\Registry::getConfig()->setConfigParam('bl_perfCalcVatOnlyForBasketOrder', false);

        // recalc basket cause of payment stuff
        if ($oBasket = $this->getBasket()) {
            $oBasket->onUpdate();
        }

        parent::init();
    }

    /**
     * Executes parent::render(), if basket is empty - redirects to main page
     * and exits the script (\OxidEsales\Eshop\Application\Model\Order::validateOrder()). Loads and passes payment
     * info to template engine. Refreshes basket articles info by additionally loading
     * each article object (\OxidEsales\Eshop\Application\Model\Order::getProdFromBasket()), adds customer
     * addressing/delivering data (\OxidEsales\Eshop\Application\Model\Order::getDelAddressInfo()) and delivery sets
     * info (\OxidEsales\Eshop\Application\Model\Order::getShipping()).
     *
     * @return string Returns name of template to render order::_sThisTemplate
     */
    public function render()
    {
        if ($this->getIsOrderStep()) {
            $oBasket = $this->getBasket();
            $myConfig = \OxidEsales\Eshop\Core\Registry::getConfig();

            if ($myConfig->getConfigParam('blPsBasketReservationEnabled')) {
                $session = \OxidEsales\Eshop\Core\Registry::getSession();
                $session->getBasketReservations()->renewExpiration();
                if (!$oBasket || ($oBasket && !$oBasket->getProductsCount())) {
                    Registry::getUtils()->redirect($myConfig->getShopHomeUrl() . 'cl=basket', true, 302);
                }
            }

            // can we proceed with ordering ?
            $oUser = $this->getUser();
            if (!$oUser && ($oBasket && $oBasket->getProductsCount() > 0)) {
                Registry::getUtils()->redirect($myConfig->getShopHomeUrl() . 'cl=basket', false, 302);
            } elseif (!$oBasket || !$oUser || ($oBasket && !$oBasket->getProductsCount())) {
                Registry::getUtils()->redirect($myConfig->getShopHomeUrl(), false, 302);
            }

            // payment is set ?
            if (!$this->getPayment()) {
                // redirecting to payment step on error ..
                Registry::getUtils()->redirect($myConfig->getShopCurrentURL() . '&cl=payment', true, 302);
            }
        }

        parent::render();

        // reload blocker
        if (!Registry::getSession()->getVariable('sess_challenge')) {
            Registry::getSession()->setVariable('sess_challenge', $this->getUtilsObjectInstance()->generateUID());
        }

        return $this->_sThisTemplate;
    }

    /**
     * Checks for order rules confirmation ("ord_agb", "ord_custinfo" form values)(if no
     * rules agreed - returns to order view), loads basket contents (plus applied
     * price/amount discount if available - checks for stock, checks user data (if no
     * data is set - returns to user login page). Stores order info to database
     * (\OxidEsales\Eshop\Application\Model\Order::finalizeOrder()). According to sum for items automatically assigns
     * user to special user group ( \OxidEsales\Eshop\Application\Model\User::onOrderExecute(); if this option is not
     * disabled in admin). Finally you will be redirected to next page (order::_getNextStep()).
     *
     * @return string|null
     */
    public function execute()
    {
        $session = \OxidEsales\Eshop\Core\Registry::getSession();
        if (!$session->checkSessionChallenge()) {
            return;
        }

        if (!$this->validateTermsAndConditions()) {
            $this->_blConfirmAGBError = 1;

            return;
        }

        // additional check if we really really have a user now
        $oUser = $this->getUser();
        if (!$oUser) {
            return 'user';
        }

        // get basket contents
        $oBasket = $session->getBasket();
        if ($oBasket->getProductsCount()) {
            try {
                $oOrder = oxNew(\OxidEsales\Eshop\Application\Model\Order::class);

                //finalizing ordering process (validating, storing order into DB, executing payment, setting status ...)
                $iSuccess = $oOrder->finalizeOrder($oBasket, $oUser);

                // performing special actions after user finishes order (assignment to special user groups)
                $oUser->onOrderExecute($oBasket, $iSuccess);

                // proceeding to next view
                return $this->getNextStep($iSuccess);
            } catch (\OxidEsales\Eshop\Core\Exception\OutOfStockException $oEx) {
                $oEx->setDestination('basket');
                Registry::getUtilsView()->addErrorToDisplay($oEx, false, true, 'basket');
            } catch (\OxidEsales\Eshop\Core\Exception\NoArticleException $oEx) {
                Registry::getUtilsView()->addErrorToDisplay($oEx);
            } catch (\OxidEsales\Eshop\Core\Exception\ArticleInputException $oEx) {
                Registry::getUtilsView()->addErrorToDisplay($oEx);
            }
        }
    }

    /**
     * Template variable getter. Returns payment object
     *
     * @return object
     */
    public function getPayment()
    {
        if ($this->_oPayment === null) {
            $this->_oPayment = false;

            $oBasket = $this->getBasket();
            $oUser = $this->getUser();

            // payment is set ?
            $sPaymentid = $oBasket->getPaymentId();
            $oPayment = oxNew(\OxidEsales\Eshop\Application\Model\Payment::class);

            if (
                $sPaymentid && $oPayment->load($sPaymentid) &&
                $oPayment->isValidPayment(
                    \OxidEsales\Eshop\Core\Registry::getSession()->getVariable('dynvalue'),
                    \OxidEsales\Eshop\Core\Registry::getConfig()->getShopId(),
                    $oUser,
                    $oBasket->getPriceForPayment(),
                    Registry::getSession()->getVariable('sShipSet')
                )
            ) {
                $this->_oPayment = $oPayment;
            }
        }

        return $this->_oPayment;
    }

    /**
     * Template variable getter. Returns active basket
     *
     * @return \OxidEsales\Eshop\Application\Model\Basket
     */
    public function getBasket()
    {
        if ($this->_oBasket === null) {
            $this->_oBasket = false;
            $session = \OxidEsales\Eshop\Core\Registry::getSession();
            if ($oBasket = $session->getBasket()) {
                $this->_oBasket = $oBasket;
            }
        }

        return $this->_oBasket;
    }

    /**
     * Template variable getter. Returns execution function name
     *
     * @return string
     */
    public function getExecuteFnc()
    {
        return 'execute';
    }

    /**
     * Template variable getter. Returns user remark
     *
     * @return string
     */
    public function getOrderRemark()
    {
        if ($this->_sOrderRemark === null) {
            $this->_sOrderRemark = false;
            if ($sRemark = Registry::getSession()->getVariable('ordrem')) {
                $this->_sOrderRemark = Registry::getConfig()->checkParamSpecialChars($sRemark);
            }
        }

        return $this->_sOrderRemark;
    }

    /**
     * Template variable getter. Returns basket article list
     *
     * @return object
     */
    public function getBasketArticles()
    {
        if ($this->_oBasketArtList === null) {
            $this->_oBasketArtList = false;
            if ($oBasket = $this->getBasket()) {
                $this->_oBasketArtList = $oBasket->getBasketArticles();
            }
        }

        return $this->_oBasketArtList;
    }

    /**
     * Template variable getter. Returns delivery address
     *
     * @return \OxidEsales\Eshop\Application\Model\Address|null
     */
    public function getDelAddress()
    {
        if ($this->_oDelAddress === null) {
            $this->_oDelAddress = false;
            $oOrder = oxNew(\OxidEsales\Eshop\Application\Model\Order::class);
            $this->_oDelAddress = $oOrder->getDelAddressInfo();
        }

        return $this->_oDelAddress;
    }

    /**
     * Template variable getter. Returns shipping set
     *
     * @return object
     */
    public function getShipSet()
    {
        if ($this->_oShipSet === null) {
            $this->_oShipSet = false;
            if ($oBasket = $this->getBasket()) {
                $oShipSet = oxNew(\OxidEsales\Eshop\Application\Model\DeliverySet::class);
                if ($oShipSet->load($oBasket->getShippingId())) {
                    $this->_oShipSet = $oShipSet;
                }
            }
        }

        return $this->_oShipSet;
    }

    /**
     * Template variable getter. Returns if option "blConfirmAGB" is on
     *
     * @return bool
     */
    public function isConfirmAGBActive()
    {
        if ($this->_blConfirmAGB === null) {
            $this->_blConfirmAGB = false;
            $this->_blConfirmAGB = \OxidEsales\Eshop\Core\Registry::getConfig()->getConfigParam('blConfirmAGB');
        }

        return $this->_blConfirmAGB;
    }

    /**
     * Template variable getter. Returns if option "blConfirmAGB" was not set
     *
     * @return bool
     */
    public function isConfirmAGBError()
    {
        return $this->_blConfirmAGBError;
    }

    /**
     * Template variable getter. Returns if option "blShowOrderButtonOnTop" is on
     *
     * @return bool
     */
    public function showOrderButtonOnTop()
    {
        if ($this->_blShowOrderButtonOnTop === null) {
            $this->_blShowOrderButtonOnTop = false;
            $this->_blShowOrderButtonOnTop = \OxidEsales\Eshop\Core\Registry::getConfig()->getConfigParam('blShowOrderButtonOnTop');
        }

        return $this->_blShowOrderButtonOnTop;
    }

    /**
     * Returns wrapping options availability state (TRUE/FALSE)
     *
     * @return bool
     */
    public function isWrapping()
    {
        if (!$this->getViewConfig()->getShowGiftWrapping()) {
            return false;
        }

        if ($this->_iWrapCnt === null) {
            $this->_iWrapCnt = 0;

            $oWrap = oxNew(\OxidEsales\Eshop\Application\Model\Wrapping::class);
            $this->_iWrapCnt += $oWrap->getWrappingCount('WRAP');
            $this->_iWrapCnt += $oWrap->getWrappingCount('CARD');
        }

        return (bool) $this->_iWrapCnt;
    }

    /**
     * Returns Bread Crumb - you are here page1/page2/page3...
     *
     * @return array
     */
    public function getBreadCrumb()
    {
        $aPaths = [];
        $aPath = [];

        $iBaseLanguage = Registry::getLang()->getBaseLanguage();
        $aPath['title'] = Registry::getLang()->translateString('ORDER', $iBaseLanguage, false);
        $aPath['link'] = $this->getLink();

        $aPaths[] = $aPath;

        return $aPaths;
    }

    /**
     * Return error number
     *
     * @return int
     */
    public function getAddressError()
    {
        return Registry::getRequest()->getRequestEscapedParameter('iAddressError');
    }

    /**
     * Return users setted delivery address md5
     *
     * @return string
     */
    public function getDeliveryAddressMD5()
    {
        // bill address
        $oUser = $this->getUser();
        $sDelAddress = $oUser->getEncodedDeliveryAddress();

        // delivery address
        if (Registry::getSession()->getVariable('deladrid')) {
            $oDelAdress = oxNew(\OxidEsales\Eshop\Application\Model\Address::class);
            $oDelAdress->load(Registry::getSession()->getVariable('deladrid'));

            $sDelAddress .= $oDelAdress->getEncodedDeliveryAddress();
        }

        return $sDelAddress;
    }

    /**
     * Method returns object with explanation marks for articles in basket.
     *
     * @return BasketContentMarkGenerator
     */
    public function getBasketContentMarkGenerator()
    {
        return oxNew(BasketContentMarkGenerator::class, $this->getBasket());
    }

    /**
     * Returns next order step. If ordering was sucessfull - returns string "thankyou" (possible
     * additional parameters), otherwise - returns string "payment" with additional
     * error parameters.
     *
     * @param integer $iSuccess status code
     *
     * @return  string  $sNextStep  partial parameter url for next step
     */
    protected function getNextStep($iSuccess)
    {
        $sNextStep = 'thankyou';

        //little trick with switch for multiple cases
        switch (true) {
            case ($iSuccess === \OxidEsales\Eshop\Application\Model\Order::ORDER_STATE_MAILINGERROR):
                $sNextStep = 'thankyou?mailerror=1';
                break;
            case ($iSuccess === \OxidEsales\Eshop\Application\Model\Order::ORDER_STATE_INVALIDDELADDRESSCHANGED):
                $sNextStep = 'order?iAddressError=1';
                break;
            case ($iSuccess === \OxidEsales\Eshop\Application\Model\Order::ORDER_STATE_BELOWMINPRICE):
                $sNextStep = 'order';
                break;
            case ($iSuccess === \OxidEsales\Eshop\Application\Model\Order::ORDER_STATE_VOUCHERERROR):
                $sNextStep = 'basket';
                break;
            case ($iSuccess === \OxidEsales\Eshop\Application\Model\Order::ORDER_STATE_PAYMENTERROR):
                // no authentication, kick back to payment methods
                Registry::getSession()->setVariable('payerror', 2);
                $sNextStep = 'payment?payerror=2';
                break;
            case ($iSuccess === \OxidEsales\Eshop\Application\Model\Order::ORDER_STATE_ORDEREXISTS):
                break; // reload blocker activ
            case (is_numeric($iSuccess) && $iSuccess > 3):
                Registry::getSession()->setVariable('payerror', $iSuccess);
                $sNextStep = 'payment?payerror=' . $iSuccess;
                break;
            case (!is_numeric($iSuccess) && $iSuccess):
                //instead of error code getting error text and setting payerror to -1
                Registry::getSession()->setVariable('payerror', -1);
                $iSuccess = urlencode($iSuccess);
                $sNextStep = 'payment?payerror=-1&payerrortext=' . $iSuccess;
                break;
            default:
                break;
        }

        return $sNextStep;
    }

    /**
     * Validates whether necessary terms and conditions checkboxes were checked.
     *
     * @return bool
     */
    protected function validateTermsAndConditions()
    {
        $blValid = true;
        $oConfig = \OxidEsales\Eshop\Core\Registry::getConfig();

        if ($oConfig->getConfigParam('blConfirmAGB') && !Registry::getRequest()->getRequestEscapedParameter('ord_agb')) {
            $blValid = false;
        }

        if ($oConfig->getConfigParam('blEnableIntangibleProdAgreement')) {
            $oBasket = $this->getBasket();

            $blDownloadableProductsAgreement = Registry::getRequest()->getRequestEscapedParameter('oxdownloadableproductsagreement');
            if ($blValid && $oBasket->hasArticlesWithDownloadableAgreement() && !$blDownloadableProductsAgreement) {
                $blValid = false;
            }

            $blServiceProductsAgreement = Registry::getRequest()->getRequestEscapedParameter('oxserviceproductsagreement');
            if ($blValid && $oBasket->hasArticlesWithIntangibleAgreement() && !$blServiceProductsAgreement) {
                $blValid = false;
            }
        }

        return $blValid;
    }

    /**
     * @return UtilsObject
     */
    protected function getUtilsObjectInstance()
    {
        return Registry::getUtilsObject();
    }
}
