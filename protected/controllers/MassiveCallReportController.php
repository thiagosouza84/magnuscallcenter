<?php
/**
 * Acoes do modulo "Cdr".
 *
 * MagnusSolution.com <info@magnussolution.com>
 * 17/08/2012
 */

/*
atualizad a tegorização das ligaçoes em CDR
UPDATE `pkg_cdr` SET id_category =11 WHERE calledstation IN (
SELECT number
FROM pkg_phonenumber
WHERE id_category =11
)
 */

class MassiveCallReportController extends BaseController
{
    public $attributeOrder = 'id DESC';
    public $extraValues    = array('idUser' => 'username',
    );

    public $fieldsFkReport = array(
        'id_user' => array(
            'table'       => 'pkg_user',
            'pk'          => 'id',
            'fieldReport' => "CONCAT(username, ' ', name) ",
        ),
    );

    public function init()
    {
        $this->instanceModel = new MassiveCallPhoneNumber;
        $this->abstractModel = MassiveCallPhoneNumber::model();
        $this->titleReport   = Yii::t('yii', 'Massive Call Phone Number');

        parent::init();
    }

    public function extraFilterCustom($filter)
    {
        $filter .= ' AND status > 1';

        if (Yii::app()->session['isOperator']) {
            $filter = $this->extraFilterCustomOperator($filter);
        } else if (Yii::app()->session['isClient']) {
            $filter = $this->extraFilterCustomClient($filter);
        }

        return $filter;
    }

}
