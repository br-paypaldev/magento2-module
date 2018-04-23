<?php
namespace PayPalBR\PayPal\Api;

interface WebHookManagementInterface
{
    /**
     * POST for WebHook api
     * @param mixed $id
     * @param mixed $create_time
     * @param mixed $resource_type
     * @param mixed $event_type
     * @param mixed $summary
     * @param mixed $resource
     * @param mixed $links
     * @param mixed $event_version
     * @return mixed
     */
    public function postWebHook($id, $create_time, $resource_type, $event_type, $summary, $resource, $links, $event_version);
}