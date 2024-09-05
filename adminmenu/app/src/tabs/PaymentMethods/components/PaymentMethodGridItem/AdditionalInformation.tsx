import React from "react";
import {MergedPaymentMethodObject} from "../../../../types/PaymentMethod";

const AdditionalInformation = ({mergedPaymentMethodObject}: {mergedPaymentMethodObject: MergedPaymentMethodObject}) => {

    return (
        <>
            <p><b>Informationen</b></p>
            <p className="flex justify-between mr-2"><span>Mollie-API</span><span>{mergedPaymentMethodObject?.api ? (mergedPaymentMethodObject.api === "payment" ? "Payment-API" : "Order-API") : "Keine"}</span></p>
            <p className="flex justify-between mr-2"><span>Fälligkeit in Tagen</span><span>{mergedPaymentMethodObject.dueDays} Tagen</span></p>
            <p className="flex justify-between mr-2"><span>Minimum</span><span>{mergedPaymentMethodObject?.mollie?.minimumAmount ? (mergedPaymentMethodObject?.mollie?.minimumAmount?.value + " " + mergedPaymentMethodObject?.mollie?.minimumAmount?.currency): "Unbekannt"}</span></p>
            <p className="flex justify-between mr-2"><span>Maximum</span><span>{mergedPaymentMethodObject?.mollie?.maximumAmount ? (mergedPaymentMethodObject?.mollie?.maximumAmount?.value + " " + mergedPaymentMethodObject?.mollie?.maximumAmount?.currency): "Unbekannt"}</span></p>
        </>
    )
}

export default AdditionalInformation