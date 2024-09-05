import React from "react";
import QuickStats from "./QuickStats";
import ActivePaymentMethods from "./ActivePaymentMethods";
import AboCloudWidget from "./AboCloudWidget";

const CustomDashboardGridCards = () => {
    return (
        <>
            <AboCloudWidget />
            <QuickStats />
            <ActivePaymentMethods />
        </>
    )
}


export default CustomDashboardGridCards;


