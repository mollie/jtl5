import {MergedPaymentMethodObject} from "../../../../types/PaymentMethod";
import {PaymentMethod2img} from "../../../../helper";
import {FontAwesomeIcon} from "@fortawesome/react-fontawesome";
import {faCheckCircle, faChevronDown, faChevronUp, faXmarkCircle, faCircleInfo} from "@fortawesome/pro-solid-svg-icons";
import React, {useState} from "react";
import PluginInfo from "@webstollen/react-jtl-plugin/lib/types/PluginInfo";
import ConfigurationItems from "./ConfigurationItems";
import AdditionalInformation from "./AdditionalInformation";
import Alert from "@webstollen/react-jtl-plugin/lib/components/Alert";

const PaymentMethodGridItem = ({mergedPaymentMethodObject, pluginInfo}: {mergedPaymentMethodObject: MergedPaymentMethodObject, pluginInfo: PluginInfo}) => {
    const [isOpen, setIsOpen] = useState(false)

    return (
        <div className="rounded-md p-3" style={{ background: "white", height: "fit-content", boxShadow: "rgba(17, 12, 46, 0.15) 0px 48px 100px 0px"}}>
            <div onClick={() => setIsOpen(prevState => !prevState)} className="flex items-center cursor-pointer">
                <span className="my-auto"><PaymentMethod2img mollieMethodId={(mergedPaymentMethodObject.mollie.id ?? "") as string} style={{height: 37}} /></span>
                <span className="ml-3">{mergedPaymentMethodObject.paymentMethod.cName === 'Klarna' ? 'One Klarna' : mergedPaymentMethodObject.paymentMethod.cName}</span>
                <span className="ml-2"><FontAwesomeIcon icon={isOpen ? faChevronUp: faChevronDown} className="cursor-pointer font-lg" color="black" /></span>
                <StatusIcon mergedPaymentMethodObject={mergedPaymentMethodObject}/>
            </div>
            <div className="leading-7" style={{ background: "transparent", overflow: "hidden", transition: "all 300ms ease 0s", ...(isOpen ? { maxHeight: 1000, marginTop: "0.75rem", marginBottom: "0.75rem" } : {maxHeight: 0, margin: 0})}}>
                { mergedPaymentMethodObject?.paymentMethod?.cName === 'Giropay' ?
                    // TODO remove with Giropay within the next Update
                    <>
                        <Alert variant={"error"}>Diese Zahlungsart ist bei Mollie nicht länger verfügbar. Sie wird im Checkout nicht weiter angezeigt und mit dem nächsten Update gelöscht.</Alert>
                        <br />
                    </>
                    :
                    <>
                        <ConfigurationItems mergedPaymentMethodObject={mergedPaymentMethodObject} pluginInfo={pluginInfo} />
                        <br />
                        <AdditionalInformation mergedPaymentMethodObject={mergedPaymentMethodObject} />
                    </>
                }
            </div>
        </div>
    )
}


const StatusIcon = ({mergedPaymentMethodObject}: {mergedPaymentMethodObject: MergedPaymentMethodObject}) => {

    const paymentDuringCheckoutAllowed = ["Creditcard", "Banktransfer"]

    let icon;
    if (mergedPaymentMethodObject.paymentMethod.nActive !== "1" || mergedPaymentMethodObject.mollie?.status !== "activated" || (mergedPaymentMethodObject?.linkedShippingMethods?.length ?? 0) === 0) {
        icon = <FontAwesomeIcon icon={faXmarkCircle} className="font-lg" color="red"/>
    } else if (mergedPaymentMethodObject.paymentMethod.nWaehrendBestellung !== "1" && !paymentDuringCheckoutAllowed.includes(mergedPaymentMethodObject?.paymentMethod?.cName ?? "")) {
        icon = <FontAwesomeIcon icon={faCircleInfo} className="font-lg" color="gold" title={'Es wird empfohlen auf "Bestellung vor Bestellabschluss" umzustellen!'}/>
    } else {
        icon = <FontAwesomeIcon icon={faCheckCircle} className="font-lg" color="green"/>
        if (mergedPaymentMethodObject.paymentMethod.cName === "Giropay") {
            icon = <FontAwesomeIcon icon={faXmarkCircle} className="font-lg" color="red"/>
        }
    }

    return (
        <span className="ml-auto mr-1" style={{fontSize: '25px'}}>{icon}</span>
    )
}

export default PaymentMethodGridItem