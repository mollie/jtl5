import {FontAwesomeIcon} from "@fortawesome/react-fontawesome";
import {faCheckCircle, faGear, faLink, faXmarkCircle} from "@fortawesome/pro-solid-svg-icons";
import React from "react";
import PluginInfo from "@webstollen/react-jtl-plugin/lib/types/PluginInfo";
import {MergedPaymentMethodObject} from "../../../../types/PaymentMethod";

const ConfigurationItems = ({mergedPaymentMethodObject, pluginInfo}: {mergedPaymentMethodObject: MergedPaymentMethodObject, pluginInfo: PluginInfo}) => {

    const mollieProfileSettingsPageUrl = "https://my.mollie.com/dashboard/settings/profiles/"

    const shippingPageUrl =
        pluginInfo.shopVersionEqualOrGreaterThan520
            ? pluginInfo.adminURL + "/shippingmethods"
            : pluginInfo.adminURL + "/versandarten.php"


    const paymentSettingsPageUrl = pluginInfo.shopVersionEqualOrGreaterThan520
        ? pluginInfo.adminURL + "/paymentmethods?kZahlungsart=" + mergedPaymentMethodObject.paymentMethod.kZahlungsart + "&token=" + pluginInfo.token
        : pluginInfo.adminURL + "/zahlungsarten.php?kZahlungsart=" + mergedPaymentMethodObject.paymentMethod.kZahlungsart + "&token=" + pluginInfo.token

    return (
        <>
            <p className="flex items-center gap-2"><b>Konfiguration</b><a href={paymentSettingsPageUrl} target="_blank" rel="noreferrer"><FontAwesomeIcon icon={faGear} className="cursor-pointer font-lg float-right mr-2" color={"black"} /></a></p>
            <p>Bei Mollie aktiv <a href={mollieProfileSettingsPageUrl}><span className="cursor-pointer">
                {mergedPaymentMethodObject.mollie.status === "activated"
                    ? <FontAwesomeIcon icon={faCheckCircle} className="font-lg float-right mr-2" color="green"/>
                    : <span className="cursor-pointer">
                            <FontAwesomeIcon icon={faXmarkCircle} className="font-lg float-right mr-2" color="red" />
                            <FontAwesomeIcon icon={faLink} className="font-lg float-right mr-2" color="black"/>
                        </span>
                }</span></a>
            </p>
            <p>Im Shop aktiv <a href={paymentSettingsPageUrl} target="_blank" className="cursor-pointer" rel="noreferrer">{mergedPaymentMethodObject.paymentMethod.nActive === "1" ?
                <FontAwesomeIcon icon={faCheckCircle} className="font-lg float-right mr-2" color="green"/> :
                <FontAwesomeIcon icon={faXmarkCircle} className="font-lg float-right mr-2" color="red"/>}
                </a>
            </p>
            <p>Versandart verknüpft <a href={shippingPageUrl} target="_blank" className="cursor-pointer" rel="noreferrer">{mergedPaymentMethodObject.linkedShippingMethods.length > 0
                ? <FontAwesomeIcon icon={faCheckCircle} className="font-lg float-right mr-2" color="green"/>
                : <span>
                        <FontAwesomeIcon icon={faXmarkCircle} className="font-lg float-right mr-2" color="red"/>
                        <FontAwesomeIcon icon={faLink} className="font-lg float-right mr-2" color="black"/>
                    </span>}
                </a>
            </p>
            {mergedPaymentMethodObject?.allowDuringCheckout
                ? <p>Zahlung <u>vor</u> Bestellabschluss <a href={paymentSettingsPageUrl} target="_blank" className="cursor-pointer" rel="noreferrer">{mergedPaymentMethodObject.paymentMethod.nWaehrendBestellung === "1"
                        ? <FontAwesomeIcon icon={faCheckCircle} className="font-lg float-right mr-2" color="green" />
                        : <>
                            <FontAwesomeIcon icon={faXmarkCircle} className="font-lg float-right mr-2" color="gold" title={'Es wird empfohlen auf "Bestellung vor Bestellabschluss" umzustellen!'}/>
                            <FontAwesomeIcon icon={faLink} className="font-lg float-right mr-2" color="black"/>
                        </>
                    }
                    </a>
                </p>
                : <p>Zahlung <u>nach</u> Bestellabschluss <a href={paymentSettingsPageUrl} target="_blank" className="cursor-pointer" rel="noreferrer">{mergedPaymentMethodObject.paymentMethod.nWaehrendBestellung  === "0"
                        ? <FontAwesomeIcon icon={faCheckCircle} className="font-lg float-right mr-2" color="green" />
                        : <>
                            <FontAwesomeIcon icon={faXmarkCircle} className="font-lg float-right mr-2" color="red" title={'Diese Zahlungsart funktioniert nur mit "Zahlung nach Bestellabschluss"!'}/>
                            <FontAwesomeIcon icon={faLink} className="font-lg float-right mr-2" color="black"/>
                            </>
                        }
                    </a>
                </p>
            }
        </>
    )
}

export default ConfigurationItems