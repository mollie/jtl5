import {jtlStatus2text, Label, usePluginInfo} from "@webstollen/react-jtl-plugin/lib";
import React from "react";
import creditcardImg from './assets/img/creditcard.svg';
import applypayImg from './assets/img/applepay.svg';
import bancontactImg from './assets/img/bancontact.svg';
import belfiusImg from './assets/img/belfius.svg';
import directdebitImg from './assets/img/directdebit.svg';
import epsImg from './assets/img/eps.svg';
import giftcardImg from './assets/img/giftcard.svg';
import giropayImg from './assets/img/giropay.svg';
import idealImg from './assets/img/ideal.svg';
import inghomepayImg from './assets/img/inghomepay.svg';
import kbcImg from './assets/img/kbc.svg';
import klarnaImg from './assets/img/klarna.svg';
import mybankImg from './assets/img/mybank.svg';
import paypalImg from './assets/img/paypal.svg';
import paysafecardImg from './assets/img/paysafecard.svg';
import Przelewy24Img from './assets/img/Przelewy24.svg';
import sofortImg from './assets/img/sofort.svg';


export type MollieOrder = {
    bSynced: boolean
    bTest: boolean
    cBestellNr: string
    cCurrency: string
    cHash: string
    cLocale: string
    cMethod: string
    cOrderId: string
    cStatus: string
    cThirdId: string
    cTransactionId: string
    dCreated: string
    fAmount: string
    fAmountRefunded: number
    kBestellung: number
    kId: number

    cJTLStatus: string
    cAbgeholt: string
    cVersandartName: string
    cZahlungsartName: string
    fGesamtsumme: number
    fGuthaben: number

    // cIP: string
    // cKommentar: string
    // cLogistiker: string
    // dBezahltDatum: string | null
    // kSprache: number
    // kVersandart: number
    // kZahlungsart: number
    // kWaehrung: number
    // kWarenkorb: number
};

export type ColorType = "green" | "black" | "gray" | "orange" | "red" | "blue" | "white" | undefined;

export const molliePaymentStatusLabel = (status: string) => {
    let color: ColorType = undefined;
    switch (status) {
        case 'paid':
        case 'authorized':
            color = 'green';
            break;
        case 'pending':
            color = 'orange';
            break;
        case 'open':
            color = 'blue';
            break;
        case 'canceled':
        case 'expired':
            color = 'gray';
            break;
        case 'failed':
            color = 'red';
            break;
    }
    return <Label className={'inline'} color={color}>{status}</Label>
}

export const jtlStatus2label = (status: string | number) => {
    const nStatus = typeof status === 'string' ? parseInt(status) : status;
    let color: ColorType = undefined;
    switch (nStatus) {
        case -1:
            color = 'red';
            break;
        case 1:
        case 2:
            color = 'orange';
            break;
        case 3:
            color = 'blue'
            break;
        case 4:
        case 5:
            color = 'green';
            break;

    }
    return <Label color={color} className={"inline"}>{jtlStatus2text(`${status}`)}</Label>;
}

export const PaymentMethod2img = ({method}: { method: string }) => {

    const pInfo = usePluginInfo();

    if (pInfo.endpoint) {
        const prefix = pInfo.endpoint.substring(0, pInfo.endpoint.lastIndexOf('/')) + '/app/build';
        const images = {
            applepay: applypayImg,
            bancontact: bancontactImg,
            belfius: belfiusImg,
            creditcard: creditcardImg,
            directdebit: directdebitImg,
            eps: epsImg,
            giftcard: giftcardImg,
            giropay: giropayImg,
            ideal: idealImg,
            inghomepay: inghomepayImg,
            kbc: kbcImg,
            klarna: klarnaImg,
            mybank: mybankImg,
            paypal: paypalImg,
            paysafecard: paysafecardImg,
            Przelewy24: Przelewy24Img,
            sofort: sofortImg,
        }
        switch (method) {
            case 'applepay':
            case 'bancontact':
            case 'belfius':
            case 'creditcard':
            case 'directdebit':
            case 'eps':
            case 'giftcard':
            case 'giropay':
            case 'ideal':
            case 'inghomepay':
            case 'kbc':
            case 'mybank':
            case 'paypal':
            case 'paysafecard':
            case 'Przelewy24':
            case 'sofort':
                return <img className={'inline'} src={prefix + images[method]} title={method} alt={method}/>
            case 'klarnasliceit':
            case 'klarnapaylater':
                return <img className={'inline'} src={prefix + images.klarna} title={method} alt={method}/>
        }
    }
    return <pre>{method}</pre>;
}