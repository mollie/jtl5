import React from 'react'
import Table, { ItemTemplate } from '@webstollen/react-jtl-plugin/lib/components/Table'
import { formatAmount, Loading } from '@webstollen/react-jtl-plugin/lib'
import { mollieOrderLineTypeLabel, molliePaymentStatusLabel, OrderLineType } from '../../../helper'
import Button from '@webstollen/react-jtl-plugin/lib/components/Button'
import { UseMollieReturn } from '../../../hooks/useMollie'
import useErrorSnack from '../../../hooks/useErrorSnack'
import DataTable, { DataTableHeader } from '@webstollen/react-jtl-plugin/lib/components/DataTable/DataTable'

export type OrderLinesProps = {
  mollie: UseMollieReturn
}

type Variation = {
  name: string
  value: string
  kEigenschaft: number
  kEigenschaftWert: number
}

const OrderLines = ({ mollie }: OrderLinesProps) => {
  console.debug('(OrderLines->render)', mollie)
  const [showError] = useErrorSnack()
  const handleCancelOrderLine = (lineId: string, max: number) => {
    const quantity =
      max > 1
        ? parseFloat(
            window.prompt(`Wieviele von max. ${max} möchten Sie abbrechen?`, `${max}`)?.replace(',', '.') ?? '0'
          )
        : 1
    if (quantity <= max) {
      if (window.confirm(`Möchten Sie wirklich diese Position ${quantity}x unwiderruflich abbrechen?`)) {
        mollie.cancelOrderLine(lineId, quantity).catch((e) => showError(`${e}`))
      }
    } else {
      alert(`Sie können maximal ${max} abbrechen.`)
    }
  }

  const handleCancelOrder = () => {
    if (window.confirm('Diese Bestellung bei Mollie wirklich abbrechen?')) {
      mollie.cancelOrder().catch((e) => showError(`${e}`))
    }
  }

  const handleRefundOrder = () => {
    if (window.confirm('Diese Bestellung bei Mollie wirklich zurück erstatten?')) {
      mollie.refundOrder().catch((e) => showError(`${e}`))
    }
  }

  const handleRefundOrderLine = (lineId: string, max: number) => {
    const quantity =
      max > 1
        ? parseFloat(
            window.prompt(`Wieviele von max. ${max} möchten Sie abbrechen?`, `${max}`)?.replace(',', '.') ?? '0'
          )
        : 1
    if (quantity <= max) {
      if (window.confirm(`Möchten Sie wirklich diese Position ${quantity}x unwiderruflich abbrechen?`)) {
        mollie.refundOrderLine(lineId, quantity).catch((e) => showError(`${e}`))
      }
    } else {
      alert(`Sie können maximal ${max} abbrechen.`)
    }
  }

  return (
    <Loading loading={mollie.loading}>
      <div className="mt-4">
        <div className="flex justify-between mb-2">
          <h3 className="font-bold text-2xl mb-1">Positionen</h3>
          {mollie.data?.isCancelable && (
            <Button color="orange" onClick={handleCancelOrder}>
              cancel Order
            </Button>
          )}
          {['paid', 'completed'].includes(mollie.data?.status) && (
            <Button color="red" onClick={handleRefundOrder}>
              refund Order
            </Button>
          )}
        </div>
        <DataTable striped fullWidth header={header}>
          {mollie.data?.lines.map((row: Record<string, any>) => (
            <tr>
              <td>{row.id ?? '-'}</td>
              <td className="text-center">{row.status ? molliePaymentStatusLabel(row.status) : '-'}</td>
              <td>
                {row.sku ?? '-'}
                <br />
                {row.type ? mollieOrderLineTypeLabel(row.type as OrderLineType) : '-'}
              </td>
              <td>
                {row.name ?? '-'}
                {row.metadata?.properties?.length
                  ? row.metadata?.properties.map((prop: Variation) => (
                      <>
                        <br />
                        <b>{JSON.stringify(prop.name)}</b>: <i>{JSON.stringify(prop.value)}</i>
                      </>
                    ))
                  : null}
              </td>
              <td>
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
              </td>
              <td className="text-right">{parseFloat(row.vatRate)}%</td>
              <td className="text-right">{formatAmount(row.vatAmount.value, 2, row.vatAmount.currency)}</td>
              <td>
                <b>{formatAmount(row.totalAmount.value, 2, row.totalAmount.currency)}</b>
              </td>
              <td>
                {row?.cancelableQuantity > 0 && (
                  <Button color="orange" onClick={() => handleCancelOrderLine(row.id, row.cancelableQuantity ?? 0)}>
                    cancel
                  </Button>
                )}
                {row?.refundableQuantity > 0 && (
                  <Button color="red" onClick={() => handleRefundOrderLine(row.id, row.refundableQuantity ?? 0)}>
                    refund
                  </Button>
                )}
              </td>
            </tr>
          ))}
        </DataTable>
      </div>
    </Loading>
  )
}

const header: Array<DataTableHeader> = [
  {
    title: 'ID',
    column: 'id',
  },
  {
    title: 'Status',
    column: 'status',
  },
  {
    title: 'SKU',
    column: 'sku',
  },
  {
    title: 'Name',
    column: 'name',
  },
  {
    title: 'Anzahl',
    column: 'quantity',
  },
  {
    title: 'MwSt.',
    column: 'vatRate',
  },
  {
    title: 'Steuer.',
    column: 'tax',
  },
  {
    title: 'Brutto',
    column: 'brutto',
  },
  {
    title: '',
    column: '_actions',
  },
]

export default OrderLines
