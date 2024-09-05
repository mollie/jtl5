import React from "react";
import {FontAwesomeIcon} from "@fortawesome/react-fontawesome";
import {faCircleInfo} from "@fortawesome/pro-solid-svg-icons";
import {Loading} from "@webstollen/react-jtl-plugin/lib";
import {MergedPaymentMethodObject} from "../../types/PaymentMethod";
import PaymentMethodGrid from "./components/PaymentMethodGrid";
import {usePaymentMethods} from "../../context/PaymentMethodContext";


const PaymentMethods = () => {
    const {paymentMethods} = usePaymentMethods()

    // Split into active and inactive payment methods
    let activeMethods: Array<MergedPaymentMethodObject> = []
    let inactiveAndInvalidMethods: Array<MergedPaymentMethodObject> = []

    paymentMethods &&
    Object.values(paymentMethods).forEach((paymentMethod) => {
        if (
            paymentMethod.mollie.status === 'activated' &&
            paymentMethod.linkedShippingMethods.length &&
            paymentMethod.paymentMethod?.nActive === "1" &&
            (!paymentMethod.duringCheckout || paymentMethod.allowDuringCheckout)
        ) {
            activeMethods.push(paymentMethod)
        } else {
            inactiveAndInvalidMethods.push(paymentMethod)
        }
    })

    return (
        <div className="mx-1 py-5 rounded-md">
            <Loading className="w-full h-full relative" loading={!paymentMethods}>
                {!paymentMethods
                    ? <div style={{height: "9rem", width: "100%"}}/>
                    : <>
                        <div style={{marginBottom: 30}}>
                            <p style={{marginBottom: "1rem"}}><b>Aktive Zahlungsarten:</b></p>
                            { activeMethods && activeMethods.length > 0
                                ? <PaymentMethodGrid paymentMethods={activeMethods}/>
                                : <div className="rounded-md p-3 w-full flex items-center" style={{ height: 60, background: "white", boxShadow: "rgba(17, 12, 46, 0.15) 0px 48px 100px 0px" }}>
                                    <FontAwesomeIcon icon={faCircleInfo} className="font-lg mr-3" color="gold" style={{fontSize: 25}}/>
                                    <p>Momentan ist noch kein Mollie Zahlungsart aktiv.  Erfülle bei den gewünschten Zahlungsarten unten die roten markierten Bedingungen um die Zahlungsart zu aktivieren.</p>
                                </div>
                            }
                        </div>
                        <div>
                            <p style={{marginBottom: "1rem"}}><b>Inaktive Zahlungsarten:</b></p>
                            <PaymentMethodGrid paymentMethods={inactiveAndInvalidMethods}/>
                        </div>
                    </>
                }
            </Loading>
        </div>
    );
}

export default PaymentMethods;


