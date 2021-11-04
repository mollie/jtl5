import React from 'react'
import Table, { ItemTemplate } from '@webstollen/react-jtl-plugin/lib/components/Table'
import { formatAmount } from '@webstollen/react-jtl-plugin/lib'
import { mollieOrderLineTypeLabel, molliePaymentStatusLabel, OrderLineType } from '../../../helper'
import Button from '@webstollen/react-jtl-plugin/lib/components/Button'
import useApi from '@webstollen/react-jtl-plugin/lib/hooks/useAPI'

export type OrderLinesProps = {
  mollie: Record<string, any>
  reload?: () => void
}

type Variation = {
  name: string
  value: string
  kEigenschaft: number
  kEigenschaftWert: number
}

const OrderLines = ({ mollie, reload }: OrderLinesProps) => {
  console.debug('(OrderLines->render)')
  const api = useApi()

  const handleCancelOrderLine = (lineId: string, max: number) => {
    const quantity =
      max > 1
        ? parseFloat(
            window.prompt(`Wieviele von max. ${max} möchten Sie abbrechen?`, `${max}`)?.replace(',', '.') ?? '0'
          )
        : 1
    if (quantity === 0) {
      return
    }
    if (quantity <= max) {
      if (window.confirm(`Möchten Sie wirklich diese Position ${quantity}x unwiderruflich abbrechen?`)) {
        api
          .run('mollie', 'cancelOrderLine', {
            id: mollie.id,
            lineId: lineId,
            quantity: quantity,
          })
          .then(() => {
            console.debug('Cancelled, reload!')
            if (reload) reload()
          })
          .catch(alert)
      }
    } else {
      alert(`Sie können maximal ${max} abbrechen.`)
    }
  }

  const handleRefundOrderLine = (lineId: string, max: number) => {
    const quantity =
      max > 1
        ? parseFloat(
            window.prompt(`Wieviele von max. ${max} möchten Sie abbrechen?`, `${max}`)?.replace(',', '.') ?? '0'
          )
        : 1
    if (quantity === 0) {
      return
    }
    if (quantity <= max) {
      if (window.confirm(`Möchten Sie wirklich diese Position ${quantity}x unwiderruflich abbrechen?`)) {
        api
          .run('mollie', 'refundOrderLine', {
            id: mollie.id,
            lineId: lineId,
            quantity: quantity,
          })
          .then(() => {
            console.debug('Refunded, reload!')
            if (reload) reload()
          })
          .catch(alert)
      }
    } else {
      alert(`Sie können maximal ${max} abbrechen.`)
    }
  }

  const template = {
    id: {
      header: () => 'ID',
      data: (row) => row.id ?? '-',
    },
    status: {
      header: () => 'Status',
      data: (row) => (row.status ? molliePaymentStatusLabel(row.status) : '-'),
      align: 'center',
    },
    sku: {
      header: () => 'SKU',
      data: (row) => row.sku,
    },
    name: {
      header: () => 'Name',
      data: (row) => (
        <>
          {row.name ?? '-'}
          {row.metadata?.properties?.length
            ? row.metadata?.properties.map((prop: Variation) => (
                <>
                  <br />
                  <b>{JSON.stringify(prop.name)}</b>: <i>{JSON.stringify(prop.value)}</i>
                </>
              ))
            : null}
        </>
      ),
    },
    type: {
      header: () => 'Typ',
      data: (row) => (row.type ? mollieOrderLineTypeLabel(row.type as OrderLineType) : '-'),
      align: 'center',
    },
    quantity: {
      header: () => 'Anzahl',
      data: (row) => {
        return (
          <div>
            {row.quantity}
            {row.quantityCanceled > 0 ? (
              <span title="Cancelled" className="text-orange-600 font-bold px-2 cursor-help whitespace-no-wrap">
                [ {row.quantityCanceled} ]
              </span>
            ) : null}
            {row.quantityRefunded > 0 ? (
              <span title="Refunded" className="text-red-600 font-bold px-2 cursor-help whitespace-no-wrap">
                [ {row.quantityRefunded} ]
              </span>
            ) : null}
          </div>
        )
      },
      align: 'center',
    },
    vatRate: {
      header: () => 'MwSt.',
      data: (row) => `${parseFloat(row.vatRate)}%`,
      align: 'center',
    },
    vatAmount: {
      header: () => 'Steuer',
      data: (row) => formatAmount(row.vatAmount.value, 2, row.vatAmount.currency),
      align: 'right',
    },
    netto: {
      header: () => 'Netto',
      data: (row) => formatAmount(row.totalAmount.value - row.vatAmount.value, 2, row.totalAmount.currency),
      align: 'right',
    },
    brutto: {
      header: () => 'Brutto',
      data: (row) => <b>{formatAmount(row.totalAmount.value, 2, row.totalAmount.currency)}</b>,
      align: 'right',
    },
    actions: {
      header: () => '',
      data: (row) => {
        if (row?.cancelableQuantity > 0) {
          return (
            <Button color="orange" onClick={() => handleCancelOrderLine(row.id, row.cancelableQuantity ?? 0)}>
              cancel
            </Button>
          )
        }
        if (row?.refundableQuantity > 0) {
          return (
            <Button color="red" onClick={() => handleRefundOrderLine(row.id, row.refundableQuantity ?? 0)}>
              refund
            </Button>
          )
        }
      },
      align: 'center',
    },
  } as Record<string, ItemTemplate<Record<string, any>>>

  return (
    <div className="mt-4">
      <h3 className="font-bold text-2xl mb-1">Positionen</h3>
      <Table template={template} items={mollie.lines} />
      TEST: <pre>{JSON.stringify(mollie.lines, null, 2)}</pre>
    </div>
  )
}

export default OrderLines
