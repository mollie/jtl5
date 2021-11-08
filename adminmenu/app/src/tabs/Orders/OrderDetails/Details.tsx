import React from 'react'
import moment from 'moment'
import TextLink from '@webstollen/react-jtl-plugin/lib/components/TextLink'
import { formatAmount, Loading } from '@webstollen/react-jtl-plugin/lib'
import { molliePaymentStatusLabel } from '../../../helper'
import { UseMollieReturn } from '../../../hooks/useMollie'

export type DetailsProps = {
  mollie: UseMollieReturn
}

const Details = ({ mollie }: DetailsProps) => {
  return (
    <Loading loading={mollie.loading}>
      <table className="w-full my-2">
        <tbody>
          <tr>
            <th>Mollie ID:</th>
            <td>
              <TextLink target="_blank" color="blue" href={mollie.data?._links.dashboard?.href}>
                {mollie.data?.id}
              </TextLink>
            </td>
            <th>Mode:</th>
            <td>{mollie.data?.mode}</td>
            <th>Status:</th>
            <td>{molliePaymentStatusLabel(mollie.data?.status)}</td>
          </tr>
          <tr>
            <th>Betrag:</th>
            <td>{formatAmount(mollie.data?.amount.value, 2, mollie.data?.amount.currency)}</td>
            <th>Captured:</th>
            <td>
              {mollie.data?.amountCaptured
                ? formatAmount(mollie.data?.amountCaptured.value, 2, mollie.data?.amountCaptured.currency)
                : '-'}
            </td>
            <th>Refunded:</th>
            <td>
              {mollie.data?.amountRefunded
                ? formatAmount(mollie.data?.amountRefunded.value, 2, mollie.data?.amountRefunded.currency)
                : '-'}
            </td>
          </tr>
          <tr>
            <th>Method:</th>
            <td>{mollie.data?.method}</td>
            <th>Locale:</th>
            <td>{mollie.data?.locale}</td>
            <th>Erstellt:</th>
            <td>{moment(mollie.data?.createdAt).format('Do MMM YYYY, HH:mm:ss')} Uhr</td>
          </tr>
          <tr>
            {mollie.data?.billingAddress ? (
              <>
                <th>Kunde:</th>
                <td>
                  {mollie.data?.billingAddress.title} {mollie.data?.billingAddress.givenName}{' '}
                  {mollie.data?.billingAddress.familyName}
                </td>
              </>
            ) : null}
            <th>Zahlungslink:</th>
            <td colSpan={3}>
              {mollie.data?._links.checkout?.href ? (
                <TextLink target="_blank" color="red" href={mollie.data?._links.checkout?.href}>
                  {mollie.data?._links.checkout.href}
                </TextLink>
              ) : (
                '-'
              )}
            </td>
          </tr>
        </tbody>
      </table>
    </Loading>
  )
}

export default Details
