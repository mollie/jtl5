import {MergedPaymentMethodObject} from "../../../types/PaymentMethod";
import React from "react";
import {usePluginInfo} from "@webstollen/react-jtl-plugin/lib";
import PaymentMethodGridItem from "./PaymentMethodGridItem";

const PaymentMethodGrid = ({paymentMethods}: {paymentMethods: Array<MergedPaymentMethodObject>}) => {
    const pluginInfo = usePluginInfo();

    return (
        <div className="grid gap-5 grid-cols-3">
            {paymentMethods?.map((mergedPaymentMethodObject: MergedPaymentMethodObject) => {
                return (
                    <PaymentMethodGridItem key={mergedPaymentMethodObject?.paymentMethod?.cName ?? ""} mergedPaymentMethodObject={mergedPaymentMethodObject} pluginInfo={pluginInfo}/>
                )
            })}
        </div>
    )
}

export default PaymentMethodGrid