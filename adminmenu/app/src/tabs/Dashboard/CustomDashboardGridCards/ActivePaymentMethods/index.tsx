import React from "react";
import DashboardGridCard from "@webstollen/react-jtl-plugin/lib/components/DashboardPage/DashboardGridCard";
import {FontAwesomeIcon} from "@fortawesome/react-fontawesome";
import {faSync} from "@fortawesome/pro-regular-svg-icons";
import {Loading} from "@webstollen/react-jtl-plugin/lib";
import {faCheckCircle, faCircleInfo, faXmarkCircle} from "@fortawesome/pro-solid-svg-icons";
import {MergedPaymentMethodObject} from "../../../../types/PaymentMethod";
import {PaymentMethod2img} from "../../../../helper";
import {usePaymentMethods} from "../../../../context/PaymentMethodContext";

const ActivePaymentMethods = () => {
    const {paymentMethods, reFetchPaymentMethods} = usePaymentMethods()

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
        <DashboardGridCard>
            <div className="w-full h-full relative">{/* This div only exists to prevent shadow from affecting other DashboardGridCards due to position:relative */}
                <Loading loading={!paymentMethods} className={"h-full"}>
                    <div className="absolute" style={{right: 5, top: 5, zIndex: 2}}>
                        <FontAwesomeIcon
                            onClick={reFetchPaymentMethods}
                            spin={!paymentMethods}
                            icon={faSync}
                            size={'lg'}
                            className="float-right cursor-pointer"
                            title="aktualisieren"
                        />
                    </div>
                    <div style={{flexDirection: "column", justifyContent: "space-between", gap: 20, height: "100%"}} className="flex gap-1">
                        {!!paymentMethods &&
                            <>
                                <div style={{marginBottom: 10}}>
                                    <p style={{marginBottom: "1rem"}}><FontAwesomeIcon icon={faCheckCircle} className="font-lg mr-1" color="green"/><b>Aktive Zahlungsarten:</b></p>
                                    {activeMethods
                                        ? <div
                                            className="flex gap-1 flex-wrap">{activeMethods?.map((mergedPaymentMethodObject) =>
                                            <span key={mergedPaymentMethodObject?.paymentMethod?.cName ?? ""} className="my-auto">
                                                <PaymentMethod2img
                                                    mollieMethodId={(mergedPaymentMethodObject.mollie.id ?? "") as string}
                                                    style={{height: 37}}
                                                />
                                            </span>)}
                                        </div>
                                        : <div className="rounded-md p-3 w-full flex items-center" style={{ height: 60, background: "white", boxShadow: "rgba(17, 12, 46, 0.15) 0px 48px 100px 0px" }}>
                                            <FontAwesomeIcon icon={faCircleInfo} className="font-lg mr-3" color="gold" style={{fontSize: 25}}/>
                                            <p>Momentan ist noch kein Mollie Zahlungsart aktiv.  Erfülle bei den gewünschten Zahlungsarten unten die roten markierten Bedingungen um die Zahlungsart zu aktivieren.</p>
                                        </div>
                                    }
                                </div>
                                <div>
                                    <p style={{marginBottom: "1rem"}}><FontAwesomeIcon icon={faXmarkCircle} className="font-lg mr-1" color="red"/><b>Inaktive Zahlungsarten:</b></p>
                                    <div className="flex gap-1 flex-wrap">
                                        {inactiveAndInvalidMethods?.map(
                                            (mergedPaymentMethodObject) => <span key={mergedPaymentMethodObject?.paymentMethod?.cName ?? ""} className="my-auto">
                                                <PaymentMethod2img
                                                    mollieMethodId={(mergedPaymentMethodObject.mollie.id ?? "") as string}
                                                    style={{height: 37}}
                                                />
                                            </span>)}
                                    </div>
                                </div>
                            </>
                        }
                    </div>
                </Loading>
            </div>
        </DashboardGridCard>
    )
}

export default ActivePaymentMethods;


