import React, {useEffect, useState} from "react";
import Table, {ItemTemplate} from "@webstollen/react-jtl-plugin/lib/components/Table";
import {useAPI} from "@webstollen/react-jtl-plugin/lib";
import Button from "@webstollen/react-jtl-plugin/lib/components/Button";
import {FontAwesomeIcon} from "@fortawesome/react-fontawesome";
import {faShippingFast, faSync} from "@fortawesome/pro-regular-svg-icons";
import TextLink from "@webstollen/react-jtl-plugin/lib/components/TextLink";

export type ShipmentsProps = {
    kBestellung: number
    mollie: Record<string, any>
}

const Shipments = ({mollie, kBestellung}: ShipmentsProps) => {


    const api = useAPI();
    const [shipments, setShipments] = useState([]);

    useEffect(() => {
        api.run('orders', 'shipments', {
            'kBestellung': kBestellung
        }).then(result => {
            console.log(result);
            setShipments(result.data);
        }).catch(err => {
            console.log(err);
        })
    }, [api, kBestellung]);

    const syncShipping = (kLieferschein: number) => {
        api.run('shipments', 'sync', {
            kLieferschein: kLieferschein,
            orderId: mollie.id,
            kBestellung: kBestellung,
        })
            .then(resp => {
                if (resp.data.error) {
                    alert(resp.data.error.message)
                } else {
                    console.log(resp)
                }
            })
            .catch(err => {
                alert(err.message);
            })
    }

    if (!shipments.length) {
        return <p>No shipments!</p>;
    }

    const template = {
        lsNr: {
            header: () => 'LieferscheinNr',
            data: row => row.shipment ?
                <TextLink color={"green"} href={""} target='_blank'>row.cLieferscheinNr</TextLink> : row.cLieferscheinNr
        },
        shipmentId: {
            header: () => 'Shipment ID',
            data: row => row.shipment?.id ? row.shipment?.id : '-'
        },
        carrier: {
            header: () => 'Carrier',
            data: row => row.tracking ? row.tracking.carrier : 'n/a'
        },
        code: {
            header: () => 'Code',
            data: row => row.tracking ? row.tracking.code : 'n/a'
        },
        actions: {
            header: () => ' ',
            align: 'right',
            data: row => {
                const actions: React.ReactNode[] = [];
                if (mollie.status !== 'completed') {
                    actions.push(<Button title='synchronize' onClick={() => syncShipping(row.kLieferschein)}>
                        <FontAwesomeIcon icon={faSync}/>
                    </Button>);
                }
                if (row.cUrl) {
                    actions.push(<Button><FontAwesomeIcon icon={faShippingFast}/></Button>)
                }
                return <>{actions}</>
            }//row.tracking?.url ? <TextLink href={row.tracking.url}>O</TextLink> : '-'
        },
    } as Record<string, ItemTemplate<Record<string, any>>>

    return <Table striped template={template} items={shipments}/>;
}

export default Shipments;