<?php
/**
 * Acoes do modulo "Call".
 *
 * =======================================
 * ###################################
 * MagnusCallCenter
 *
 * @package MagnusCallCenter
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2012 - 2018 MagnusCallCenter. All rights reserved.
 * ###################################
 *
 * This software is released under the terms of the GNU Lesser General Public License v2.1
 * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
 *
 * Please submit bug reports, patches, etc to https://github.com/magnussolution/magnuscallcenter/issues
 * =======================================
 * MagnusCallCenter.com <info@magnussolution.com>
 * 19/09/2012
 */

class OperatorStatusController extends BaseController
{
    public $attributeOrder = 't.id DESC';
    public $extraValues    = array('idUser' => 'username,name', 'idCampaign' => 'name');

    public function init()
    {
        $this->instanceModel = new OperatorStatus;
        $this->abstractModel = OperatorStatus::model();
        $this->titleReport   = Yii::t('yii', 'OperatorStatus');
        parent::init();
    }

    public function setAttributesModels($attributes, $models)
    {
        for ($i = 0; $i < count($attributes) && is_array($attributes); $i++) {

            if ($attributes[$i]['categorizing'] == 1) {
                $attributes[$i]['time_free'] = time() - $attributes[$i]['time_start_cat'];
            } else if ($attributes[$i]['queue_paused'] == 1 && ($attributes[$i]['categorizing'] == 0 || $attributes[$i]['categorizing'] == null)) {
                $modelLoginsCampaign = LoginsCampaign::model()->find(
                    "id_user = " . $attributes[$i]['id_user'] . " AND login_type = 'PAUSE' AND stoptime = '0000-00-00 00:00:00'"
                );

                if (count($modelLoginsCampaign)) {
                    $attributes[$i]['pause_type'] = isset($modelLoginsCampaign->idBreak->name) ? $modelLoginsCampaign->idBreak->name : 'INVALID';
                    $attributes[$i]['time_free']  = time() - strtotime($modelLoginsCampaign->starttime);
                } else {
                    OperatorStatus::model()->updateByPk($attributes[$i]['id'], array('queue_paused' => 0));
                }
            } elseif ($attributes[$i]['queue_status'] == 2 || $attributes[$i]['queue_status'] == 6 || $attributes[$i]['queue_status'] == 1) {
                $attributes[$i]['time_free'] = time() - $attributes[$i]['time_free'];
            } else {
                $attributes[$i]['time_free'] = '';
            }

        }
        return $attributes;

    }

    //Verifica o status atual do operadora
    public function actionOperatorCheckStatus()
    {

        $modelOperatorStatus = OperatorStatus::model()->find("id_user = " . Yii::app()->session['id_user']);

        if (count($modelOperatorStatus) > 0) {

            if ($modelOperatorStatus->idUser->force_logout == 1) {
                $status                            = 'LOGOUT';
                Yii::app()->session['id_campaign'] = null;
                //User::model()->updateByPk(Yii::app()->session['id_user'], array('force_logout' => 0));
            } else if ($modelOperatorStatus->categorizing == 1) {
                $status = 'CATEGORIZING';
            } else if ($modelOperatorStatus->queue_paused == 1) {
                $status = 'PAUSED';
            } else {
                switch ($modelOperatorStatus->queue_status) {
                    case 1:
                        $status = 'NOT_INUSE';
                        break;
                    case 2:
                        $status = 'INUSE';
                        break;
                    case 3:
                        $status = 'BUSY';
                        break;
                    case 4:
                        $status = 'INVALID';
                        break;
                    case 5:
                        $status = 'NOT LOGED ON SOFTPHONE';
                        break;
                    case 6:
                        $status = 'RINGING';
                        break;
                    case 7:
                        $status = 'RINGINUSE';
                        break;
                    case 8:
                        $status = 'ONHOLD';
                        break;
                    default:
                        $status = 'UNKNOWN';
                        break;
                }
            }

        } elseif (count($modelOperatorStatus) == 0) {
            $modelUser = User::model()->findByPk(Yii::app()->session['id_user']);
            if ($modelUser->force_logout == 1) {
                $status                            = 'LOGOUT';
                Yii::app()->session['id_campaign'] = null;
                User::model()->updateByPk(Yii::app()->session['id_user'], array('force_logout' => 0));
            } else {
                $status = 'NO CAMPAING';
            }

        }
        //check if exist a mandaroyBreak
        Yii::import('application.controllers.BreaksController');
        $status = BreaksController::checkMandatoryBreak($status);

        $status = array('rows' => array('status' => $status[0]), 'break_madatory' => $status[1]);

        echo json_encode($status);
    }

    public function actionDestroy()
    {
        $values = $this->getAttributesRequest();

        $resultOperatorStatus = OperatorStatus::model()->findByPk($values['id']);

        $modelCampaign = Campaign::model()->findByPk($resultOperatorStatus->id_campaign);

        $id_campaign = $modelCampaign->id;

        $modelUser = User::model()->findByPk($resultOperatorStatus->id_user);
        $username  = $modelUser->username;

        if ($id_campaign > 0) {

            AsteriskAccess::instance()->queueRemoveMember($username, $modelCampaign->name);

            $modelPhonenumber = PhoneNumber::model()->findByPk($modelUser->id_current_phonenumber);
            if (count($modelPhonenumber)) {
                $modelPhonenumber->id_user = null;
                $modelPhonenumber->save();
            }

            $modelUser->id_current_phonenumber = null;
            $modelUser->id_campaign            = null;
            $modelUser->force_logout           = 1;
            $modelUser->save();

            $modelLoginsCampaign = LoginsCampaign::model()->find(
                "id_user = :id_user AND stoptime = :stoptime AND
                    id_campaign = :id_campaign AND login_type = :login_type",
                array(
                    ":id_campaign" => $modelCampaign->id,
                    ":id_user"     => $modelUser->id,
                    ":stoptime"    => '0000-00-00 00:00:00',
                    ":login_type"  => 'LOGIN',
                ));
            if (count($modelLoginsCampaign)) {
                $modelLoginsCampaign->stoptime   = date('Y-m-d H:i:s');
                $modelLoginsCampaign->total_time = strtotime(date('Y-m-d H:i:s')) - strtotime($modelLoginsCampaign->starttime);
                try {
                    $modelLoginsCampaign->save();
                } catch (Exception $e) {
                    Yii::log(print_r($modelLoginsCampaign->errors, true), 'info');
                }
            }

            LoginsCampaign::model()->deleteAll('id_user = :key AND stoptime = :key1', array(
                ':key'  => $modelUser->id,
                ':key1' => '0000-00-00 00:00:00',
            ));

            OperatorStatus::model()->deleteAll("id_user = " . $modelUser->id);

            $success = true;
            $msn     = Yii::t('yii', 'Operation was successful.');
        }

        echo json_encode(array(
            'success' => $success,
            'msg'     => $msn,
        ));
        exit();
    }

}
