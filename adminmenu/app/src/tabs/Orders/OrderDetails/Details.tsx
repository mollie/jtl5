import React from "react";
import moment from "moment";
import TextLink from "@webstollen/react-jtl-plugin/lib/components/TextLink";
import {formatAmount} from "@webstollen/react-jtl-plugin/lib";
import {molliePaymentStatusLabel} from "../../../helper";

export type DetailsProps = {
    mollie: Record<string, any>
}

const Details = ({mollie}: DetailsProps) => {
    return <table className="w-full my-2">
        <tr>
            <th>Mollie ID:</th>
            <td>
                <TextLink target="_blank"
                          color="blue"
                          href={mollie._links.dashboard.href}>{mollie.id}</TextLink>
            </td>
            <th>Mode:</th>
            <td>{mollie.mode}</td>
            <th>Status:</th>
            <td>{molliePaymentStatusLabel(mollie.status)}</td>
        </tr>
        <tr>
            <th>Betrag:</th>
            <td>{formatAmount(mollie.amount.value, 2, mollie.amount.currency)}</td>
            <th>Captured:</th>
            <td>{mollie.amountCaptured ? formatAmount(mollie.amountCaptured.value, 2, mollie.amountCaptured.currency) : '-'}</td>
            <th>Refunded:</th>
            <td>{mollie.amountRefunded ? formatAmount(mollie.amountRefunded.value, 2, mollie.amountRefunded.currency) : '-'}</td>
        </tr>
        <tr>
            <th>Method:</th>
            <td>{mollie.method}</td>
            <th>Locale:</th>
            <td>{mollie.locale}</td>
            <th>Erstellt:</th>
            <td>{moment(mollie.createdAt).format('Do MMM YYYY, HH:mm:ss')} Uhr</td>
        </tr>
        <tr>
            <th>Kunde:</th>
            <td>{mollie.billingAddress.title} {mollie.billingAddress.givenName} {mollie.billingAddress.familyName}</td>
            <th>Zahlungslink:</th>
            <td colSpan={3}>{mollie._links.checkout ?? '-'}</td>
        </tr>
    </table>;
}

export default Details;