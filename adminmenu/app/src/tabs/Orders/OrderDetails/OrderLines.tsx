import React from "react";
import Table, {ItemTemplate} from "@webstollen/react-jtl-plugin/lib/components/Table";
import {formatAmount} from "@webstollen/react-jtl-plugin/lib";
import {molliePaymentStatusLabel} from "../../../helper";

export type OrderLinesProps = {
    mollie: Record<string, any>
}

const OrderLines = ({mollie}: OrderLinesProps) => {

    const template = {
        id: {
            header: () => 'ID',
            data: row => row.id ?? '-',
        },
        status: {
            header: () => 'Status',
            data: row => row.status ? molliePaymentStatusLabel(row.status) : '-',
            align: "center"
        },
        sku: {
            header: () => 'SKU',
            data: row => row.sku,
        },
        name: {
            header: () => 'Name',
            data: row => row.name ?? '-',
        },
        type: {
            header: () => 'Typ',
            data: row => row.type ?? '-',
            align: "center"
        },
        quantity: {
            header: () => 'Anzahl',
            data: row => row.quantity ?? '-',
            align: "center"
        },
        vatRate: {
            header: () => 'MwSt.',
            data: row => `${parseFloat(row.vatRate)}%`,
            align: "center"
        },
        vatAmount: {
            header: () => 'Steuer',
            data: row => formatAmount(row.vatAmount.value, 2, row.vatAmount.currency),
            align: "right"
        },
        netto: {
            header: () => 'Netto',
            data: row => formatAmount(row.totalAmount.value - row.vatAmount.value, 2, row.totalAmount.currency),
            align: "right"
        },
        brutto: {
            header: () => 'Brutto',
            data: row => <b>{formatAmount(row.totalAmount.value, 2, row.totalAmount.currency)}</b>,
            align: "right"
        }
    } as Record<string, ItemTemplate<Record<string, any>>>;

    return <Table template={template} items={mollie.lines}/>;
}

export default OrderLines;