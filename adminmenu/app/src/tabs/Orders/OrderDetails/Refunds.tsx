import React from "react";
import Table, {ItemTemplate} from "@webstollen/react-jtl-plugin/lib/components/Table";
import {formatAmount} from "@webstollen/react-jtl-plugin/lib";
import {molliePaymentStatusLabel} from "../../../helper";

export type RefundsProps = {
    mollie: Record<string, any>
}

const Refunds = ({mollie}: RefundsProps) => {

    const template = {
        id: {
            header: () => 'ID',
            data: row => <pre>
                {row.id}
            </pre>
        },
        status: {
            header: () => 'Status',
            data: row => molliePaymentStatusLabel(row.status),
        },
        description: {
            header: () => 'Description',
            data: row => row.description,
        },
        amount: {
            header: () => 'Amount',
            data: row => formatAmount(row.amount.value, 2, row.amount.currency),
        },
        created: {
            header: () => 'Created',
            data: row => row.createdAt,
        }
    } as Record<string, ItemTemplate<Record<string, any>>>;

    return <Table striped
                  template={template} items={mollie._embedded.refunds}/>;
}

export default Refunds;