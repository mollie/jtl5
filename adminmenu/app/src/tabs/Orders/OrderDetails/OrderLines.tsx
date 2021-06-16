import React from "react";
import Table, {ItemTemplate} from "@webstollen/react-jtl-plugin/lib/components/Table";
import {formatAmount} from "@webstollen/react-jtl-plugin/lib";
import {mollieOrderLineTypeLabel, molliePaymentStatusLabel, OrderLineType} from "../../../helper";

export type OrderLinesProps = {
  mollie: Record<string, any>
}

type Variation = {
  name: string
  value: string
  kEigenschaft: number
  kEigenschaftWert: number
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
      data: row => <>{row.name ?? '-'}
        {row.metadata?.properties?.length ? row.metadata?.properties.map((prop: Variation) => <>
          <br/><b>{JSON.stringify(prop.name)}</b>: <i>{JSON.stringify(prop.value)}</i></>) : null}</>,
    },
    type: {
      header: () => 'Typ',
      data: row => row.type ? mollieOrderLineTypeLabel(row.type as OrderLineType) : '-',
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