import {FontAwesomeIcon} from "@fortawesome/react-fontawesome";
import React from "react";
import {MethodProps} from "./index";
import {faExclamationTriangle} from "@fortawesome/pro-solid-svg-icons";
import {
  faCog,
  faCreditCard,
  faEnvelopeOpenDollar,
  faEnvelopeOpenText,
  faInfoCircle,
  faMoneyBill,
  faShippingFast
} from "@fortawesome/pro-regular-svg-icons";
import {PaymentMethod2img, showMethodInfo} from "../../../helper";


const Valid = ({method}: { method: MethodProps }) => {
  return <div key={method.mollie.id} style={{flexBasis: '33%'}}>
    <div className="m-2 p-2 border-b">

      {method.paymentMethod
      && method.duringCheckout && !method.allowDuringCheckout
      && <FontAwesomeIcon className={"mr-4 ml-1 cursor-help"} icon={faExclamationTriangle}
                          color={"red"}
                          onClick={() => alert('Zahlung vor Bestellabschluss wird nicht unterstützt. Diese Zahlungsart wird nicht zur auswahl stehen.')}
                          title={"Zahlung vor Bestellabschluss nicht unterstützt."}/>}

      <PaymentMethod2img method={method.mollie.id}/> {method.mollie.description}

      <FontAwesomeIcon icon={faInfoCircle} title={'Informationen anzeigen'} size={"sm"}
                       className="ml-2 cursor-help" onClick={() => showMethodInfo(method)}/>


      <div className="float-right">

        {method.duringCheckout ?
            <FontAwesomeIcon icon={faEnvelopeOpenDollar}
                             className={"ml-1 cursor-help"}
                             title={"Zahlung vor Bestellabschluss"}
                             color={method.allowDuringCheckout ? 'green' : 'red'}/>
            : <FontAwesomeIcon icon={faEnvelopeOpenText}
                               className={"ml-1 cursor-help"}
                               title={"Zahlung nach Bestellabschluss"}
                               color={method.allowDuringCheckout ? 'blue' : 'green'}/>}

        {method.components ? <>
          {method.components === 'S' || method.components === 'Y' ?
              <FontAwesomeIcon className={"ml-1 cursor-help"} icon={faCreditCard}
                               onClick={() => alert("Mollie Components sind aktiviert: " + (method.components === 'S' ? 'optional.' : 'obligatorisch.'))}
                               title={"Mollie Components enabled." + (method.components === 'S' ? ' (optional)' : ' (obligatorisch)')}
                               color={method.components === 'S' ? 'green' : 'blue'}/>
              : <FontAwesomeIcon className={"ml-1 cursor-help"} icon={faCreditCard}
                                 onClick={() => alert("Mollie Components sind deaktiviert.")}
                                 color={'red'}
                                 title={'Mollie Components disabled'}/>
          }
        </> : null}
        {method.api ? (
            method.api === 'payment' ?
                <FontAwesomeIcon className={"cursor-help ml-1"} icon={faMoneyBill}
                                 onClick={() => alert("Payment API ist aktiviert.")}
                                 title={'Payment API'} color={"green"}/> : null
        ) : null}

        <FontAwesomeIcon icon={faShippingFast}
                         onClick={() => alert(method.shipping && method.shipping?.length ? "Mit folgende Versandarten verknüpft:\n\n" + method.shipping.map((shipping: Record<string, any>) => ' - ' + shipping.cName).join("\n") : 'Mit keiner Versandart verknüpft!')}
                         className="cursor-help ml-1"
                         color={method.shipping && method.shipping?.length ? 'green' : 'red'}
                         title={method.shipping && method.shipping?.length ? method.shipping.map((shipping: Record<string, any>) => shipping.cName).join(', ') : 'n/a'}/>

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

export default Valid;