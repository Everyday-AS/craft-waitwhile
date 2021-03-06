<?php

namespace everyday\waitwhile\controllers;

use craft\web\Controller;
use everyday\waitwhile\models\Guest;
use everyday\waitwhile\models\Waitwhile;

class QueueController extends Controller
{
    protected $allowAnonymous = true;

    /**
     * @return false|string|\yii\web\Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \craft\errors\MissingComponentException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionIndex()
    {
        $isJavascript = (bool)\Craft::$app->request->getHeaders()['javascript-request'] ||
            \Craft::$app->request->getIsAjax();

        $params = \Craft::$app->request->getBodyParams();
        $phone = $params['phone'] ?? null;

        // if landcode hidden input field is set, append this to phone number unless phone number starts with +
        if ($phone !== null && isset($params['country_code']) && substr($phone, 0, strlen('+')) !== '+') {
            $phone = '+' . $params['country_code'] . $phone;
        }

        $guest = (new Guest())
            ->setEmail($params['email'] ?? null)
            ->setPhone($phone)
            ->setNotes($params['notes'] ?? null)
            ->setBirthdate($params['birthdate'] ?? null)
            ->setName($params['name']);

        if ($guest->validate()) {
            $waitwhile = new Waitwhile();

            $response = $waitwhile->createWaitingGuest($guest);

            if (!$waitwhile->error) {
                \Craft::$app->getSession()->set('waitwhile', $response);

                if (!$isJavascript) {
                    return $this->redirect(isset($params['redirect']) ? $params['redirect'] : '/');
                }

                return json_encode(['success' => true]);
            }

            // error:
            if (!$isJavascript) {
                return \Craft::$app->urlManager->setRouteParams(array(
                    'errors' => $waitwhile->errors
                ));
            }

            return json_encode(['success' => false, 'errors' => array_values(call_user_func_array('array_merge', $waitwhile->errors))]);
        }

        // error
        if (!$isJavascript) {
            return \Craft::$app->urlManager->setRouteParams(array(
                'errors' => $guest->errors
            ));
        }

        return json_encode(['success' => false, 'errors' => array_values(call_user_func_array('array_merge', $guest->errors))]);
    }
}
