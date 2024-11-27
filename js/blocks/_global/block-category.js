import { getCategories, setCategories } from "@wordpress/blocks";
import BrandIcon from "../../../brand/components/BrandIcon";
import "./editor.scss";

setCategories([
  ...getCategories().filter(({ slug }) => slug !== "memberpress"),
  {
    slug: "memberpress",
    title: "MemberPress",
    icon: <BrandIcon />
  }
]);
