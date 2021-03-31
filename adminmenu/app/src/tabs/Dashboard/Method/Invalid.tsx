import React from "react";
import {MethodProps} from "./index";
import {faExclamationTriangle, faTimesOctagon} from "@fortawesome/pro-solid-svg-icons";
import {PaymentMethod2img, showMethodInfo} from "../../../helper";
import {faCog, faInfoCircle} from "@fortawesome/pro-regular-svg-icons";
import {FontAwesomeIcon} from "@fortawesome/react-fontawesome";

const Invalid = ({method}: { method: MethodProps }) => {

    const errors : string[] = [];
    if (!method.shipping.length) {
        errors.push("Keine Versandarten verknüpft.")
    }
    if (!method.paymentMethod) {
        errors.push("Keine Zahlungsart im Shop gefunden.")
    }
    if (method.paymentMethod && parseInt(method.paymentMethod.nWaehrendBestellung) >= 0) {
        errors.push("Zahlung vor Bestellabschluss leider nicht möglich.")
    }

    return <div key={method.mollie.id} style={{flexBasis: '33%'}}>
        <div className="m-2 p-2 border-b">
            {method.mollie.status !== 'activated' ?
                <FontAwesomeIcon className={"mr-4 ml-1 cursor-help"}
                                 icon={faTimesOctagon}
                                 color={"red"}
                                 onClick={() => alert('Zahlungsart bei Mollie nicht aktiv.')}
                                 title={"Zahlungsart bei Mollie nicht aktiv."}/>
                : <FontAwesomeIcon className={"mr-4 ml-1 cursor-help"}
                                   icon={faExclamationTriangle}
                                   color={"red"}
                                   onClick={() => alert(errors.join('\n'))}
                                   title={errors.join(' ')}/>}

            <PaymentMethod2img method={method.mollie.id}/> <span
            className={method.mollie.status !== 'activated' ? "text-red-400" : ''}>{method.mollie.description}</span>

            <FontAwesomeIcon icon={faInfoCircle} title={'Informationen anzeigen'} size={"sm"}
                             className="ml-2 cursor-help" onClick={() => showMethodInfo(method)}/>

            <div className="float-right">
                {method.paymentMethod === false ? null :
                    <a title="Einstellungen" href={method.settings}
                       className={"ml-1"}
                       target="_blank" rel="noreferrer">
                        <FontAwesomeIcon icon={faCog}/>
                    </a>}
            </div>

        </div>
    </div>
}

export default Invalid;