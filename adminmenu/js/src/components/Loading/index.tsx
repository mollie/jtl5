import React from 'react';
import cls from './Loading.module.scss';

type Props = {
    loading?: boolean
    children: React.ReactNode
    [key: string]: any
}

const Loading = (props: Props) => {

    return <div className={props.className}
                onClick={props.onClick}>
        {props.loading && <div className={cls.loading}>
            <div className={cls.spinner}/>
        </div>}
        {props.children}
    </div>;

}

export default Loading;