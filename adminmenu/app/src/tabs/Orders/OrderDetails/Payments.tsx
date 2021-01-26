import React from "react";
import Table, {ItemTemplate} from "@webstollen/react-jtl-plugin/lib/components/Table";
import TextLink from "@webstollen/react-jtl-plugin/lib/components/TextLink";
import {formatAmount} from "@webstollen/react-jtl-plugin/lib";
import {molliePaymentStatusLabel} from "../../../helper";
import {faEdit} from "@fortawesome/pro-regular-svg-icons";
import {FontAwesomeIcon} from "@fortawesome/react-fontawesome";

export type PaymentsProps = {
    mollie: Record<string, any>
}

const Payments = ({mollie}: PaymentsProps) => {

    const template = {
        id: {
            header: () => 'ID',
            data: row => <TextLink target="_blank"
                                   color="blue"
                                   href={row._links.dashboard?.href}>{row.id}</TextLink>,
        },
        status: {
            header: () => 'Status',
            data: row => molliePaymentStatusLabel(row.status),
        },
        method: {
            header: () => 'Methode',
            data: row => row.method,
        },
        amount: {
            header: () => 'Betrag',
            data: row => formatAmount(row.amount.value, 2, row.amount.currency),
        },
        settlement: {
            header: () => 'Settlement',
            data: row => row.settlementAmount?.value ? formatAmount(row.settlementAmount.value, 2, row.settlementAmount.currency) : '-',
        },
        refunded: {
            header: () => 'Refunded',
            data: row => row.amountRefunded?.value ? formatAmount(row.amountRefunded.value, 2, row.amountRefunded.currency) : '-',
        },
        remaining: {
            header: () => 'Remaining',
            data: row => row.amountRemaining?.value ? formatAmount(row.amountRemaining.value, 2, row.amountRemaining.currency) : '-',
        },
        details: {
            header: () => 'Details',
            data: row => <pre>{JSON.stringify(row.details, null, 2)}</pre>
        },
        actions: {
            header: () => ' ',
            data: row => row._links.changePaymentState ?
                <TextLink href={row._links.changePaymentState.href} target="_blank">
                    <FontAwesomeIcon icon={faEdit}/>
                </TextLink> : ''
        }
    } as Record<string, ItemTemplate<Record<string, any>>>

    return <Table striped
                  template={template} items={mollie._embedded.payments}/>;
}

export default Payments;