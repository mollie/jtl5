import React, {useEffect, useState} from "react";
import useApi from "@webstollen/react-jtl-plugin/lib/hooks/useAPI";
import Alert from "@webstollen/react-jtl-plugin/lib/components/Alert";
import {faExclamation} from "@fortawesome/pro-solid-svg-icons";
import {Loading} from "@webstollen/react-jtl-plugin/lib";
import {ApiError} from "../../../helper";
import Payments from "./Payments";
import Details from "./Details";
import OrderLines from "./OrderLines";

export type OrderDetailsProps = {
    id: string
    onClose?: (event: React.MouseEvent<HTMLAnchorElement, MouseEvent>) => void
}
const OrderDetails = (props: OrderDetailsProps) => {

    const [data, setData] = useState<null | Record<string, any>>(null);
    const [error, setError] = useState<ApiError | null>(null);
    const [loading, setLoading] = useState(false);
    const api = useApi();

    useEffect(() => {
        setError(null);
        setData(null);
        if (props.id) {
            setLoading(true);
            api.run("orders", "one", {
                id: props.id
            })
                .then(res => {
                    setData(res.data);
                    setError(null)
                })
                .catch(setError)
                .finally(() => setLoading(false));
        }
    }, [props.id]);

    if (error !== null) {
        return <Alert variant={"error"} icon={{icon: faExclamation}}>Fehler beim laden der Bestellung
            "{props.id}": {error.message}</Alert>;
    }

    return <div className="relative flex-col mb-3 rounded-md w-full">
        <Loading loading={loading} className="rounded-md">
            <div className="flex-row bg-black p-3 rounded-md text-white font-bold text-2xl">
                <div className="flex-grow">
                    Bestellung: {data?.order.cBestellNr} (<pre className="inline text-ws_gray-light">{props.id}</pre>)
                </div>
                <a onClick={props.onClose}>X</a>
            </div>
            <div className=" rounded-md">

                {data && data.mollie && <Details mollie={data.mollie}/>}

                {data && data.mollie && <div>
                    <h3 className="font-bold text-2xl mb-1">Zahlungen</h3>
                    <Payments mollie={data.mollie}/>
                </div>}

                {data && data.mollie && <div>
                    <h3 className="font-bold text-2xl mb-1">Positionen</h3>
                    <OrderLines mollie={data.mollie}/>
                </div>}

                {/*<pre style={{overflow: "scroll", maxHeight: "500px"}}>{JSON.stringify(data, null, 2)}</pre>*/}
            </div>
        </Loading>
    </div>;
}

export default OrderDetails;