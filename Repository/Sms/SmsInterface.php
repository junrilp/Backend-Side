<?php
namespace App\Repository\Sms;

interface SmsInterface
{
    public function sendPhoneSMSVerification(String $mobileNumber, String $ipAddress);
    public function generatePinCode();
    public function findOrCreateSMSRecord();
    public function sendSMSPinCode();
    public function updateSMSRecord();
    public function verifyPinCode(String $mobileNumber, String $pinCode);
}