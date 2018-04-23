<?php
namespace PayPalBR\PayPal\Api;

/**
 * PayPalBR PayPalPlus Event Handler
 *
 * @category   PayPalBR
 * @package    PayPalBR_PayPalPlus
 * @author Dev
 */
use PayPal\Api\WebhookEvent;

interface EventsInterface
{
    /**
     * Process the given $webhookEvent
     *
     * @param \PayPal\Api\WebhookEvent $webhookEvent
     */
    public function processWebhookRequest(WebhookEvent $webhookEvent);

    /**
     * Get supported webhook events
     *
     * @return array
     */
    public function getSupportedWebhookEvents();

}