import { Placeholder } from "@wordpress/components";
import BrandIcon from "../../../../../brand/components/BrandIcon";

export default ({
  icon,
  label,
  instructions,
  children,
  iconClass = "mp-icon-placeholder"
}) => (
  <Placeholder
    className="mp-placeholder"
    icon={icon}
    label={
      <div>
        <BrandIcon className={iconClass} />
        {label}
      </div>
    }
    instructions={instructions}
  >
    {children}
  </Placeholder>
);
