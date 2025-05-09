<?php
namespace rnwcinv\htmlgenerator\fields\subfields;
class PDFPaymentMethodSubField extends PDFSubFieldBase {
    public function GetTestFieldValue()
    {
        return "Credit";
    }

    public function GetWCFieldName()
    {
        return "payment_method_title";
    }

    public function GetRealFieldValue($format = '')
    {
        $value= $this->orderValueRetriever->get($this->GetWCFieldName());
        return $this->orderValueRetriever->TranslateText('PaymentMethod',$value,$value);
    }


}